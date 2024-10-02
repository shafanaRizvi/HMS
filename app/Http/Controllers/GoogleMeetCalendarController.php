<?php

namespace App\Http\Controllers;

use App\Models\EventGoogleCalendar;
use App\Models\GoogleCalendarIntegration;
use App\Models\GoogleCalendarList;
use App\Models\LiveConsultation;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Exception;
use Flash;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GoogleMeetCalendarController extends AppBaseController
{
    protected $client;

    public function __construct()
    {
        $googleOAuthPath = config('app.google_oauth_path');

        if (!empty($googleOAuthPath)) {
            $this->client = new Google_Client();
            $this->client->setApplicationName(config('app.name'));
            // $this->client->setAuthConfig(resource_path($googleOAuthPath));
            $this->client->setAccessType('offline');
            $this->client->setIncludeGrantedScopes(true);
            $this->client->setApprovalPrompt('force');
            $this->client->addScope(Google_Service_Calendar::CALENDAR);
            $this->client->setAuthConfig(resource_path(config('app.google_oauth_path')));
        }
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['googleCalendarIntegrationExists'] = GoogleCalendarIntegration::where('user_id',getLoggedInUserId())->exists();
        $data['googleCalendarLists'] = GoogleCalendarList::with('eventGoogleCalendar')->where('user_id',getLoggedInUserId())->get();

        return view('google_meet.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $liveConsultation = LiveConsultation::find($request->live_consultation_id);
        $accessToken = GoogleCalendarIntegration::where('user_id', getLoggedInUserId())->value('access_token');
        $meta = json_decode(GoogleCalendarIntegration::where('user_id', getLoggedInUserId())->value('meta'), true);

        if (empty($liveConsultation) || empty($accessToken) || empty($meta)) {
            throw new UnprocessableEntityHttpException(__('messages.google_meet.invalid_credentials'));
        }

        $date = $liveConsultation->consultation_date;
        $startTime = Carbon::parse($date);
        $endTime = $startTime->copy()->addMinutes($liveConsultation->consultation_duration_minutes);
        $startDateTime = $startTime->toRfc3339String();
        $endDateTime = $endTime->toRfc3339String();

        $results = [];

        try {
            $service = new Google_Service_Calendar($this->client);

            foreach ($meta['lists'] as $calendarId) {
                $event = new Google_Service_Calendar_Event([
                    'summary' => $meta['name'],
                    'start' => ['dateTime' => $startDateTime],
                    'end' => ['dateTime' => $endDateTime],
                    'reminders' => ['useDefault' => true],
                    'description' => $meta['description'],
                ]);

                if ($liveConsultation->platform_type == LiveConsultation::GOOGLE_MEET) {
                    $data = $service->events->insert($calendarId, $event, ['conferenceDataVersion' => 1]);

                    $conferenceRequest = new \Google_Service_Calendar_CreateConferenceRequest();
                    $conferenceRequest->setRequestId('randomString123');
                    $conference = new \Google_Service_Calendar_ConferenceData();
                    $conference->setCreateRequest($conferenceRequest);

                    $data->setConferenceData($conference);
                    $data = $service->events->patch($calendarId, $data->id, $data, ['conferenceDataVersion' => 1]);

                    $results[] = [
                        'google_meet_link' => $data->hangoutLink,
                        'google_calendar_id' => $calendarId,
                    ];
                } else {
                    $data = $service->events->insert($calendarId, $event);
                    $results[] = [
                        'google_calendar_id' => $calendarId,
                    ];
                }
            }
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            throw new UnprocessableEntityHttpException(__('messages.google_meet.event_creation_error'));
        }

        return $results;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // For redirect to Generate redirect URL
    public function oauth()
    {
        $googleOAuthPath = config('app.google_oauth_path');

        if (empty($googleOAuthPath) || !file_exists(resource_path($googleOAuthPath))) {
            Flash::error(__('messages.google_meet.validate_json_file'));
            return redirect()->back();
        }

        $authUrl = $this->client->createAuthUrl();
        $filteredUrl = filter_var($authUrl, FILTER_SANITIZE_URL);

        return redirect($filteredUrl);
    }

    public function redirect(Request $request)
    {
        try {
            DB::beginTransaction();

            $accessToken = $this->client->fetchAccessTokenWithAuthCode($request->get('code'));

            GoogleCalendarIntegration::where('user_id', getLoggedInUserId())->delete();

            $googleCalendarIntegration = GoogleCalendarIntegration::create([
                'user_id' => getLoggedInUserId(),
                'access_token' => $accessToken['access_token'],
                'last_used_at' => Carbon::now(),
                'meta' => json_encode($accessToken),
            ]);

            $this->client->setAccessToken($accessToken);
            $calendarLists = $this->fetchCalendarListAndSyncToDB();

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
        }

        Flash::success(__('messages.google_meet.google_calendar_connect'));

        return redirect(route('connect-google-calendar.index'));
    }


    // For the store multiple google Calendar
    public function fetchCalendarListAndSyncToDB()
    {
        $googleCalendarList = [];
        $service = new Google_Service_Calendar($this->client);

        $calendarList = $service->calendarList->listCalendarList();

        foreach ($calendarList->getItems() as $calendarListEntry) {
            if ($calendarListEntry->accessRole == 'owner') {
                $googleCalendarList[] = GoogleCalendarList::updateOrCreate(
                    [
                        'user_id' => getLoggedInUserId(),
                        'google_calendar_id' => $calendarListEntry->id,
                    ],
                    [
                        'calendar_name' => $calendarListEntry->summary,
                        'meta' => json_encode($calendarListEntry),
                    ]
                );
            }
        }

        return $googleCalendarList;
    }

    // For the save our google event calendar
    public function eventGoogleCalendarStore(Request $request)
    {
        $eventGoogleCalendars = EventGoogleCalendar::where('user_id',getLoggedInUserId())->get();

        if($eventGoogleCalendars){
            foreach ($eventGoogleCalendars as $eventGoogleCalendar) {
                $eventGoogleCalendar->delete();
            }
        }

        $input = $request->all();
        $googleCalendarIds = $input['google_calendar'];

        foreach ($googleCalendarIds as $googleCalendarId) {
            $googleCalendarListId = GoogleCalendarList::find($googleCalendarId)->google_calendar_id;
            $data = [
                'user_id' => getLoggedInUserId(),
                'google_calendar_list_id' => $googleCalendarId,
                'google_calendar_id' => $googleCalendarListId,
            ];

            EventGoogleCalendar::create($data);
        }

        return $this->sendSuccess(__('messages.google_meet.google_calendar_add'));
    }

    // For the Sync Google event Calendar
    public function syncGoogleCalendarList()
    {
        $this->getAccessToken(getLoggedInUserId());

        $gcHelper = new Google_Service_Calendar($this->client);
        // Use the Google Client calendar service. This gives us methods for interacting
        // with the Google Calendar API
        $calendarList = $gcHelper->calendarList->listCalendarList();

        $googleCalendarList = [];

        $existingCalendars = GoogleCalendarList::where('user_id',getLoggedInUserId())
            ->pluck('google_calendar_id', 'google_calendar_id')
            ->toArray();

        foreach ($calendarList->getItems() as $calendarListEntry) {
            if ($calendarListEntry->accessRole == 'owner') {
                $exists = GoogleCalendarList::where('user_id',getLoggedInUserId())
                    ->where('google_calendar_id', $calendarListEntry['id'])
                    ->first();

                unset($existingCalendars[$calendarListEntry['id']]);

                if (! $exists) {
                    $googleCalendarList[] = GoogleCalendarList::create([
                        'user_id' => getLoggedInUserId(),
                        'calendar_name' => $calendarListEntry['summary'],
                        'google_calendar_id' => $calendarListEntry['id'],
                        'meta' => json_encode($calendarListEntry),
                    ]);
                }
            }
        }

        EventGoogleCalendar::whereIn('google_calendar_id', $existingCalendars)->delete();
        GoogleCalendarList::whereIn('google_calendar_id', $existingCalendars)->delete();

        return $this->sendSuccess(__('messages.google_meet.google_calendar_update'));
    }

    // this function use for check token expiration
    public function getAccessToken($userId)
    {
        $user = User::with('gCredentials')->find($userId);

        if(empty($user->gCredentials)){
            throw new UnprocessableEntityHttpException(__('messages.google_meet.disconnect_or_reconnect'));
        }

        $accessToken = json_decode($user->gCredentials->meta, true);

        if (is_array($accessToken) && count($accessToken) == 0) {
            throw new UnprocessableEntityHttpException(__('messages.google_meet.disconnect_or_reconnect'));
        } elseif ($accessToken == null) {
            throw new UnprocessableEntityHttpException(__('messages.google_meet.disconnect_or_reconnect'));
        }

        if (empty($accessToken['access_token'])) {
            throw new UnprocessableEntityHttpException(__('messages.google_meet.disconnect_or_reconnect'));
        }

        try {
            // Refresh the token if it's expired.
            $this->client->setAccessToken($accessToken);

            if ($this->client->isAccessTokenExpired()) {
                Log::info('expired');

                if(isset($accessToken['refresh_token'])){
                    $accessToken = $this->client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);

                }

                if (is_array($accessToken) && count($accessToken) == 0) {
                    throw new UnprocessableEntityHttpException(__('messages.google_meet.disconnect_or_reconnect'));
                } elseif ($accessToken == null) {
                    throw new UnprocessableEntityHttpException(__('messages.google_meet.disconnect_or_reconnect'));
                }

                if (empty($accessToken['access_token'])) {
                    throw new UnprocessableEntityHttpException(__('messages.google_meet.disconnect_or_reconnect'));
                }

                $calendarRecord = GoogleCalendarIntegration::where('user_id',$user->id)->first();
                $calendarRecord->update([
                    'access_token' => $accessToken['access_token'],
                    'meta' => json_encode($accessToken),
                    'last_used_at' => Carbon::now(),
                ]);
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());

            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return $accessToken['access_token'];
    }

    // For the delete / disconnect google calendar
    public function disconnectGoogleCalendar()
    {
        EventGoogleCalendar::where('user_id',getLoggedInUserId())->delete();
        GoogleCalendarIntegration::where('user_id',getLoggedInUserId())->delete();
        GoogleCalendarList::where('user_id',getLoggedInUserId())->delete();

        Flash::success(__('messages.google_meet.google_calendar_disconnect'));

        return redirect(route('connect-google-calendar.index'));
    }
}
