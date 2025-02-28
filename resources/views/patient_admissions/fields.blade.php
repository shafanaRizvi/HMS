<div class="row">

@if(isset($patientAdmission->patient_admission_id))
    {{Form::hidden('patient_id',$patientAdmission->patient_admission_id)}}
@endif

<!-- Patient Id Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('patient_id', __('messages.patient_admission.patient').':',['class' => 'form-label']) }}
        <span class="required"></span>
        {{ Form::select('patient_id', $data['patients'], null, ['class' => 'form-select select2Selector','id' => 'admissionPatientId', 'placeholder' =>  __('messages.patient_admission.patient'),'data-control' => 'select2','required',isset($patientAdmission->patient_admission_id) ? 'disabled' : '']) }}
    </div>

    <!-- Doctor Id Field -->
    @if(Auth::user()->hasRole('Doctor'))
        <input type="hidden" name="doctor_id" value="{{ Auth::user()->owner_id }}">
    @else
        <div class="form-group mb-5 col-sm-6">
            {{ Form::label('doctor_id', __('messages.patient_admission.doctor').':', ['class' => 'form-label']) }}
            <span class="required"></span>
            {{ Form::select('doctor_id',$data['doctors'], null, ['class' => 'form-select select2Selector','id' => 'admissionDoctorId', 'placeholder' => __('messages.web_home.select_doctor'),'data-control' => 'select2','required']) }}
        </div>
@endif

<!-- Admission Date Field -->
    <div class="form-group mb-5 col-sm-6">
        <input type="hidden" id="admissionPatientBirthDate"
               value="{{isset($data['patientAdmissionDate']->patient->user)?$data['patientAdmissionDate']->patient->user->dob:''}}">
        {{ Form::label('admission_date', __('messages.patient_admission.admission_date').':', ['class' => 'form-label']) }}
        <span class="required"></span>
        {{ Form::text('admission_date', null, ['class' => 'form-control bg-white','id' => 'admissionDate','required','autocomplete' => 'off', 'placeholder' => __('messages.patient_admission.admission_date')]) }}
    </div>

@isset($patientAdmission)
    <!-- Discharge Date Field -->
        <div class="form-group mb-5 col-sm-6 date-container">
            {{ Form::label('discharge_date', __('messages.patient_admission.discharge_date').':', ['class' => 'form-label']) }}
            {{ Form::text('discharge_date', null, ['class' => 'form-control bg-white','id' => 'admissionDischargeDate', 'autocomplete'=>'off', 'placeholder' => __('messages.patient_admission.admission_date')]) }}
        </div>
@endisset

<!-- Package Id Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('package_id', __('messages.patient_admission.package').':', ['class' => 'form-label']) }}
        {{ Form::select('package_id', $data['packages'], null, ['class' => 'form-select select2Selector','id' => 'admissionPackageId', 'placeholder' =>  __('messages.patient_admission.select_package'),'data-control' => 'select2']) }}
    </div>

    <!-- Insurance Id Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('insurance_id', __('messages.patient_admission.insurance').':', ['class' => 'form-label']) }}
        {{ Form::select('insurance_id', $data['insurances'], null, ['class' => 'form-select select2Selector','id' => 'admissionInsuranceId', 'placeholder' => __('messages.patient_admission.select_insurance'),'data-control' => 'select2']) }}
    </div>

    <!-- Bed Id Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('bed_id', __('messages.patient_admission.bed').':', ['class' => 'form-label']) }}
        {{ Form::select('bed_id', $data['beds'], null, ['class' => 'form-select select2Selector','id' => 'admissionBedId', 'placeholder' => __('messages.bed.select_bed'),'data-control' => 'select2']) }}
    </div>

    <!-- Policy No Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('policy_no', __('messages.patient_admission.policy_no').':', ['class' => 'form-label']) }}
        {{ Form::text('policy_no', null, ['class' => 'form-control', 'placeholder' => __('messages.patient_admission.policy_no')]) }}
    </div>

    <!-- Agent Name Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('agent_name', __('messages.patient_admission.agent_name').':', ['class' => 'form-label']) }}
        {{ Form::text('agent_name', null, ['class' => 'form-control', 'placeholder' => __('messages.patient_admission.agent_name')]) }}
    </div>

    <!-- Guardian Name Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('guardian_name', __('messages.patient_admission.guardian_name').':', ['class' => 'form-label']) }}
        {{ Form::text('guardian_name', null, ['class' => 'form-control', 'placeholder' => __('messages.patient_admission.guardian_name')]) }}
    </div>

    <!-- Guardian Relation Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('guardian_relation', __('messages.patient_admission.guardian_relation').':', ['class' => 'form-label']) }}
        {{ Form::text('guardian_relation', null, ['class' => 'form-control', 'placeholder' => __('messages.patient_admission.guardian_relation')]) }}
    </div>

    <!-- Guardian Contact Field -->
    <div class="form-group mb-5 col-sm-6 mb-5">
        {{ Form::label('guardian_contact', __('messages.patient_admission.guardian_contact').':', ['class' => 'form-label']) }}
        {{ Form::text('guardian_contact', null, ['class' => 'form-control  iti phoneNumber', 'id' => 'phoneNumber', 'onkeyup' => 'if (/\D/g.test(this.value)) this.value = this.value.replace(/\D/g,"")']) }}
        {{ Form::hidden('prefix_code',null,['class'=>'prefix_code']) }}
        {{ Form::hidden('country_iso', null, ['class' => 'country_iso']) }}
        <span class="text-success valid-msg d-none fw-400 fs-small mt-2" id="valid-msg">✓ &nbsp; {{__('messages.valid')}}</span>
        <span class="text-danger error-msg d-none fw-400 fs-small mt-2" id="error-msg"></span>
    </div>

    <!-- Guardian Address Field -->
    <div class="form-group mb-5 col-sm-6">
        {{ Form::label('guardian_address', __('messages.patient_admission.guardian_address').':', ['class' => 'form-label']) }}
        {{ Form::text('guardian_address', null, ['class' => 'form-control', 'placeholder' => __('messages.patient_admission.guardian_address')]) }}
    </div>

    <!-- Status Field -->
    <div class="col-md-3">
        <div class="form-group mb-5">
            {{ Form::label('status', __('messages.common.status').':', ['class' => 'form-label']) }}
            <br>
            <div class="form-check form-switch">
                <input name="status" class="form-check-input is-active" value="1"
                       type="checkbox" {{(isset($patientAdmission) && ($patientAdmission->status)) ? 'checked' : ''}} {{ !isset($patientAdmission) ? 'checked' : '' }}>
            </div>
        </div>
    </div>

</div>

<div class="d-flex justify-content-end">
    {{ Form::submit(__('messages.common.save'), ['class' => 'btn btn-primary me-2', 'id' => 'saveBtn']) }}
    <a href="{{ route('patient-admissions.index') }}"
       class="btn btn-secondary">{{ __('messages.common.cancel') }}</a>
</div>
