<?php

namespace App\Repositories;

use App\Mail\AppointmentReminderMail;
use App\Mail\NotifyMailHospitalAdminForBookingAppointment;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DoctorDepartment;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Receptionist;
use App\Models\Schedule;
use App\Models\User;
use Arr;
use Carbon\Carbon;
use DB;
use Exception;
use Hash;
use Illuminate\Support\Facades\Mail;
use Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Rest\Client;

/**
 * Class appointmentRepository
 *
 * @version February 13, 2020, 5:52 am UTC
 */
class AppointmentRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'patient_id',
        'doctor_id',
        'department_id',
        'opd_date',
        'fee',
    ];

    /**
     * Return searchable fields
     */
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Appointment::class;
    }

    public function getPatients()
    {
        /** @var Patient $patients */
        $patients = Patient::with('patientUser')->get()->where('patientUser.status', '=',
            1)->pluck('patientUser.full_name', 'id')->sort();

        return $patients;
    }

    /**
     * @return Doctor
     */
    public function getDoctors($id) //get doctors list based on department
    {
        /** @var Doctor $doctors */
        $doctors = Doctor::with('doctorUser')->where('doctor_department_id', '=', $id)
            ->get()->where('doctorUser.status', '=', 1)->pluck('doctorUser.full_name', 'id')->sort();

        return $doctors;
    }

    public function getDoctorDepartments()
    {
        /** @var DoctorDepartment $departments */
        $departments = DoctorDepartment::all()->pluck('title', 'id')->sort();

        return $departments;
    }

    public function getDoctorDepartment($id)
    {
        /** @var DoctorDepartment $departments */
        $departments = Doctor::whereId($id)->with('department')->get();

        return $departments;
    }

    /**
     * @throws ConfigurationException
     */
    public function sendAppointmentReminder()
    {
        /** @var Appointment[] $appointments */
        $appointments = Appointment::with('patient.patientUser', 'doctor.doctorUser')
            ->whereDate('opd_date', '=', Carbon::tomorrow()->toDateString())
            ->get();

        $sid = config('twilio.sid');
        $token = config('twilio.token');
        $client = new Client($sid, $token);

        foreach ($appointments as $appointment) {
            try {
                $input['email'] = $appointment->patient->user->email;
                $input['patient_name'] = $appointment->patient->user->full_name;
                $input['patient_phone'] = $appointment->patient->user->phone;
                $input['appointment_date'] = $appointment->opd_date;
                $input['problem'] = $appointment->problem;
                $input['doctor_name'] = $appointment->doctor->user->full_name;

                $smsMessage = 'You have an appointment tomorrow with '.$appointment->doctor->user->full_name.
                    "\n\n Appointment Time : ".Carbon::parse($appointment->opd_date)->format('jS M, Y g:i A').
                    "\n\n Thanks";

                Mail::to($input['email'])
                    ->send(new AppointmentReminderMail('emails.appointment_reminder',
                        __('messages.new_change.doctor_appointments'),
                        $input));

                $client->messages->create(
                    $input['patient_phone'],
                    [
                        'from' => config('twilio.from_number'),
                        'body' => $smsMessage,
                    ]
                );
            } catch (Exception $e) {
                throw new UnprocessableEntityHttpException($e->getMessage());
            }
        }
    }

    /**
     * @return mixed
     */
    public function getBookingSlot($inputs)
    {
        $data['bookingSlotArr'] = [];
        $bookingSlots = Appointment::whereDoctorId($inputs['doctor_id'])->whereDate('opd_date',
            $inputs['editSelectedDate'])->get();
        foreach ($bookingSlots as $bookingSlot) {
            $slotTime = Carbon::parse($bookingSlot->opd_date)->toTimeString();
            $onlyTime = \Str::substr($slotTime, 0, 5);
            $data['bookingSlotArr'][] = $onlyTime;
        }
        if (isset($inputs['editId'])) {
            $editTime = Appointment::whereId($inputs['editId'])->get();
            $editSlotTime = Carbon::parse($editTime[0]->opd_date)->toTimeString();
            $data['onlyTime'] = \Str::substr($editSlotTime, 0, 5);
        }

        return $data;
    }

    public function sendAppointmentEmailBeforeOneHour(): bool
    {
        $startTime = Carbon::now()->addHour()->toDateTimeString();
        $endTime = Carbon::now()->addHours(2)->toDateTimeString();
        /** @var Appointment $appointments */
        $appointments = Appointment::with('patient.user', 'doctor.user')
            ->where('opd_date', '>=', $startTime)
            ->where('opd_date', '<=', $endTime)
            ->get();

        foreach ($appointments as $appointment) {
            try {
                $input['patient_email'] = $appointment->patient->user->email;
                $input['patient_name'] = $appointment->patient->user->full_name;
                $input['appointment_date'] = $appointment->opd_date;
                $input['problem'] = $appointment->problem;
                $input['doctor_name'] = $appointment->doctor->user->full_name;
                $input['doctor_email'] = $appointment->doctor->user->email;

                Mail::to($input['patient_email'])
                    ->send(new AppointmentReminderMail('emails.appointment_reminder_patient',
                        __('messages.new_change.appointment_with_doctor') .$input['doctor_name'],
                        $input));
                Mail::to($input['doctor_email'])
                    ->send(new AppointmentReminderMail('emails.appointment_reminder_doctor',
                    __('messages.new_change.appointment_with_doctor') .$input['patient_name'],
                        $input));
            } catch (Exception $e) {
                throw new UnprocessableEntityHttpException($e->getMessage());
            }
        }

        return true;
    }

    public function createNotification(array $input)
    {
        try {
            $patient = Patient::with('patientUser')->where('id', $input['patient_id'])->first();
            $doctor = Doctor::with('doctorUser')->where('id', $input['doctor_id'])->pluck('user_id', 'id')->first();
            $receptionists = Receptionist::pluck('user_id', 'id')->toArray();
            $userIds = [
                $doctor => Notification::NOTIFICATION_FOR[Notification::DOCTOR],
                $patient->user_id => Notification::NOTIFICATION_FOR[Notification::PATIENT],
            ];
            foreach ($receptionists as $key => $userId) {
                $userIds[$userId] = Notification::NOTIFICATION_FOR[Notification::RECEPTIONIST];
            }

            $adminUser = User::role('Admin')->first();
            $allUsers = $userIds + [$adminUser->id => Notification::NOTIFICATION_FOR[Notification::ADMIN]];
            $users = getAllNotificationUser($allUsers);

            foreach ($users as $key => $notification) {
                if ($notification == Notification::NOTIFICATION_FOR[Notification::PATIENT]) {
                    $title = $patient->patientUser->full_name.' your appointment has been booked.';
                } else {
                    $title = $patient->patientUser->full_name.' appointment has been booked.';
                }
                addNotification([
                    Notification::NOTIFICATION_TYPE['Appointment'],
                    $key,
                    $notification,
                    $title,
                ]);
            }
        } catch (Exception $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    /**
     * @throws Throwable
     */
    public function createNewAppointment($input): bool
    {
        try {
            DB::beginTransaction();

            $appointmentDepartmentId = $input['department_id'];

            $input['department_id'] = Department::whereName('Patient')->first()->id;
            $input['dob'] = (! empty($input['dob']) || isset($input['dob'])) ? $input['dob'] : null;
            $input['phone'] = (! empty($input['phone']) || isset($input['phone'])) ? $input['phone'] : null;
            $input['password'] = Hash::make($input['password']);
            $input['tenant_id'] = User::where('username', $input['hospital_username'])->first()->tenant_id;
            $userData = Arr::only($input,
                ['first_name', 'last_name', 'gender', 'password', 'email', 'department_id', 'status', 'tenant_id']);

            $user = User::create($userData);
            if (isset($input['email'])) {
                $user->sendEmailVerificationNotification();
            }

            $patient = new Patient();
            $patient->user_id = $user->id;
            $patient->tenant_id = $user->tenant_id;
            $patient->save();

            $ownerId = $patient->id;
            $ownerType = Patient::class;
            $user->update(['owner_id' => $ownerId, 'owner_type' => $ownerType]);
            $user->assignRole($input['department_id']);

            $jsonFields = [];

            foreach ($input as $key => $value) {
                if (strpos($key, 'field') === 0) {
                    $jsonFields[$key] = $value;
                }
            }
            
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $input['doctor_id'],
                'department_id' => $appointmentDepartmentId,
                'opd_date' => $input['opd_date'],
                'problem' => $input['problem'],
                'tenant_id' => $input['tenant_id'],
                'custom_field' => $jsonFields,
            ]);

            $hospitalDefaultAdmin = User::where('username', $input['hospital_username'])->first();
            if (! empty($hospitalDefaultAdmin)) {

                $hospitalDefaultAdminEmail = $hospitalDefaultAdmin->email;
                $doctor = Doctor::whereId($input['doctor_id'])->first();

                $mailData = [
                    'booking_date' => Carbon::parse($input['opd_date'])->translatedFormat('g:i A').' '.Carbon::parse($input['opd_date'])->translatedFormat('jS M, Y'),
                    'patient_name' => $user->full_name,
                    'patient_email' => $user->email,
                    'doctor_name' => $doctor->user->full_name,
                    'doctor_department' => $doctor->department->title,
                    'doctor_email' => $doctor->user->email,
                ];

                $mailData['patient_type'] = 'New';

                Mail::to($hospitalDefaultAdminEmail)
                    ->send(new NotifyMailHospitalAdminForBookingAppointment('emails.booking_appointment_mail',
                    __('messages.new_change.notify_mail_for_patient_book'),
                        $mailData));
                Mail::to($doctor->user->email)
                    ->send(new NotifyMailHospitalAdminForBookingAppointment('emails.booking_appointment_mail',
                    __('messages.new_change.notify_mail_for_patient_book'),
                        $mailData));
            }

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    public function getDoctorLists()
    {
        /** @var Doctor $doctors */
        $doctors = Doctor::with('user')->get()->where('user.status', '=', 1)->pluck('user.full_name', 'id');

        return $doctors;
    }

    public function getDoctorList($id)
    {
        /** @var Doctor $doctors */
        $doctors = Doctor::whereId($id)->with('user')->get()->where('user.status', '=', 1)->pluck('user.full_name',
            'id');

        return $doctors;
    }

    public function filter($status)
    {
        if ($status == 'all') {
            return Appointment::with('patient', 'doctor', 'department')->orderBy('id', 'desc')->get();
        } elseif ($status == 'pending') {
            return Appointment::where('is_completed', Appointment::STATUS_PENDING)->where('patient_id',
                getLoggedInUser()->owner_id)->with('patient', 'doctor', 'department')->orderBy('id', 'desc')->get();
        } elseif ($status == 'completed') {
            return Appointment::where('is_completed', Appointment::STATUS_COMPLETED)->where('patient_id',
                getLoggedInUser()->owner_id)->with('patient', 'doctor', 'department')->orderBy('id', 'desc')->get();
        } elseif ($status == 'cancelled') {
            return Appointment::where('is_completed', Appointment::STATUS_CANCELLED)->where('patient_id',
                getLoggedInUser()->owner_id)->with('patient', 'doctor', 'department')->orderBy('id', 'desc')->get();
        } elseif ($status == 'past') {
            return Appointment::whereDate('opd_date', '<', Carbon::today())->where('patient_id',
                getLoggedInUser()->owner_id)->with('patient', 'doctor', 'department')->orderBy('id', 'desc')->get();
        } else {
            return false;
        }
    }

    public function getDoctorDepartmentForAPI()
    {
        $departments = DoctorDepartment::select('id', 'title')->where('tenant_id',getLoggedInUser()->tenant_id)->orderBy('id', 'desc')->get();

        return $departments;
    }

    public function getDepartmentDoctorList($id)
    {
        $doctors = Doctor::where('doctor_department_id', $id)->where('tenant_id',getLoggedInUser()->tenant_id)->with('doctorUser')->orderBy('id',
            'desc')->get()->where('doctorUser.status', '=',
                1);

        $data = [];
        foreach ($doctors as $doctor) {
            $data[] = $doctor->prepareDoctorData();
        }

        return $data;
    }

    public function getBookingSlotAPI($input)
    {
        $data['bookingSlotArr'] = [];
        $appointmentDate = $input['editSelectedDate'];
        $bookingSlots = Schedule::whereDoctorId($input['doctor_id'])->get();
        foreach ($bookingSlots as $bookingSlot) {
            $available_from = $appointmentDate.' '.$bookingSlot->scheduleDays[0]->available_from;
            $available_to = $appointmentDate.' '.$bookingSlot->scheduleDays[0]->available_to;
            $per_patient_time = explode(':', $bookingSlot->per_patient_time);
            $minutes = ($per_patient_time[0]) * 60 + ($per_patient_time[1]);
            $start_time = $this->appointmentParseIn($available_from);
            $end_time = $this->appointmentParseIn($available_to);
            $data['bookingSlotArr'] = $this->appointmentGetTimeIntervals($start_time, $end_time, $minutes);
        }

        return $data;
    }

    public function appointmentParseIn($time)
    {
        $date_time = new Carbon();

        $date_time->hour(Str::substr($time, 11, 5));
        $date_time->minute(Str::substr($time, 14, 5));

        return \Illuminate\Support\Carbon::parse($date_time)->format('H:i');
    }

    public function appointmentGetTimeIntervals($start_time, $end_time, $per_patient_time)
    {
        $arr = [];
        $start_time = \Illuminate\Support\Carbon::parse($start_time);
        $end_time = \Illuminate\Support\Carbon::parse($end_time);
        while ($start_time < $end_time) {
            $array = $start_time->setMinute($start_time->minute + $per_patient_time);
            $arr[] = \Illuminate\Support\Carbon::parse($array)->format('h:i A');
        }

        return $arr;
    }

}
