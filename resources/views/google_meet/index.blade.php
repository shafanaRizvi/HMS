@extends('layouts.app')
@section('title')
    {{ __('messages.google_meet.connect_calendar')}}
@endsection
@section('css')
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            @include('flash::message')
            <div class="card mb-5 mb-xl-10">
                @if(!$data['googleCalendarIntegrationExists'])
                    <div class="card-header border-0 justify-content-center cursor-pointer">
                        <div class="card-title m-0">
                            @if(getLoggedinDoctor())
                                <a href="{{ route('googleAuth') }}" data-turbo="false"
                                   class="btn btn-primary m-0">{{ __('messages.google_meet.connect_calendar') }}</a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="card-header border-0">
                        <div class="card-title m-0">
                            <span class="fs-5 fw-bold mt-3">{{ __('messages.google_meet.select_google_calendar') }}.</span>
                        </div>
                    </div>
                    <div class="row">
                        {{ Form::open(['id' => 'googleCalendarForm']) }}
                        <div class="col-12">
                            <div class="card-body p-12">
                                @foreach($data['googleCalendarLists'] as $key => $googleCalendarList)
                                    <div class="row mb-3">
                                        <div class="d-flex align-items-center">
                                            {{ Form::checkbox('google_calendar[]', $googleCalendarList->id, \App\Models\EventGoogleCalendar::where('google_calendar_list_id',$googleCalendarList->id)->exists(), ['class' => 'form-check-input me-5 google-calendar','id' => 'checkedId'.($key+1) ]) }}
                                            <label class=" cursor-pointer"
                                                   for="checkedId{{ $key+1 }}">
                                                <span>{{ $googleCalendarList->calendar_name }}</span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                                <div class="pt-5 mt-5">
                                    <div class="d-flex flex-sm-wrap flex-wrap">
                                        {{ Form::submit(__('messages.common.save'),['class' => 'btn btn-primary me-2 mb-md-0 mb-2','id'=>'googleCalendarSubmitBtn']) }}
                                        <a id="syncGoogleCalendar"
                                           class="me-2 btn btn-primary me-2 mb-md-0 mb-2">
                                            {{ __('messages.google_meet.sync_google_calendar') }}
                                        </a>
                                        <a href="{{ route('disconnectCalendar.destroy') }}" data-turbo="false"
                                           class="btn btn-danger m-0">{{ __('messages.google_meet.disconnect_google_calendar') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{ Form::close() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
