<div id="edit_incomes_modal" class="modal fade" role="dialog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="exampleModalLabel">{{ __('messages.incomes.edit_income') }}</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            {{ Form::open(['id'=>'editIncomesForm', 'files' => true]) }}
            <div class="modal-body">
                <div class="alert alert-danger d-none hide" id="editIncomeErrorsBox"></div>
                <div class="row">
                    {{ Form::hidden('id',null,['id'=>'editIncomeId']) }}
                    <div class="form-group col-sm-6 mb-5">
                        {{ Form::label('income_head', __('messages.incomes.income_head').(':'), ['class' => 'form-label']) }}
                        <span class="required"></span>
                        {{ Form::select('income_head', $incomeHeads, null, ['class' => 'form-select select2Selector', 'required', 'id' => 'editIncomeHeadId', 'placeholder' => __('messages.incomes.select_income_head'), 'data-control' => 'select2']) }}
                    </div>
                    <div class="form-group col-sm-6 mb-5">
                        {{ Form::label('name', __('messages.incomes.name').(':'), ['class' => 'form-label']) }}
                        <span class="required"></span>
                        {{ Form::text('name', null, ['class' => 'form-control','required', 'id' => 'editIncomeName', 'placeholder' => __('messages.incomes.name')]) }}
                    </div>
                    <div class="form-group col-sm-6 mb-5">
                        {{ Form::label('date', __('messages.incomes.date').(':'), ['class' => 'form-label']) }}
                        <span class="required"></span>
                        {{ Form::text('date', null, ['class' => (getLoggedInUser()->thememode ? 'bg-light form-control' : 'bg-white form-control'),'required', 'id' => 'editIncomeDate', 'autocomplete' => 'off', 'placeholder' => __('messages.incomes.date')]) }}
                    </div>
                    <div class="form-group col-sm-6 mb-5">
                        {{ Form::label('invoice_number', __('messages.incomes.invoice_number').(':'), ['class' => 'form-label']) }}
                        {{ Form::text('invoice_number', null, ['class' => 'form-control', 'id' => 'editIncomeInvoiceNumber', 'placeholder' => __('messages.incomes.invoice_number')]) }}
                    </div>
                    <div class="form-group col-sm-6 mb-5">
                        {{ Form::label('amount', __('messages.incomes.amount').(':'), ['class' => 'form-label']) }}
                        <span class="required"></span>
                        {{ Form::text('amount', null, ['id'=>'editIncomeAmount','class' => 'form-control price-input', 'autocomplete' => 'off', 'required', 'onkeyup' => 'if (/\D/g.test(this.value)) this.value = this.value.replace(/\D/g,"")', 'required', 'placeholder' => __('messages.incomes.amount')]) }}
                    </div>
                    <div class="form-group col-sm-6 mb-5">
                        {{ Form::label('attachment', __('messages.incomes.attachment').':', ['class' => 'fs-5 fw-bold mb-2 d-block']) }}
                        <?php
                        $style = 'style=';
                        $background = 'background-image:';
                        ?>
                        <div class="image-picker">
                            <div class="image previewImage" id="editIncomePreviewImage"
                            {{$style}}"{{$background}} url('{{ asset('assets/img/default_image.jpg') }}')">
                        </div>
                        <span class="picker-edit rounded-circle text-gray-500 fs-small" title="Change attachment">
                                <label>
                                <i class="fa-solid fa-pen" id="profileImageIcon"></i>
                                    <input type="file" id="editIncomeAttachments" name="attachment"
                                           class="image-upload d-none profileImage"
                                           accept=".png, .jpg, .jpeg, .gif, .webp"/>
                                    <input type="hidden" name="avatar_remove"/>
                                </label>
                            </span>
                    </div>
                </div>
            </div>
            <div class="form-group col-sm-12 mb-5">
                {{ Form::label('description', __('messages.incomes.description').(':'),['class' => 'form-label']) }}
                {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 4, 'id' => 'editIncomeDescription', 'placeholder' => __('messages.incomes.description')]) }}
            </div>
        </div>
        <div class="modal-footer pt-0">
            {{ Form::button(__('messages.common.save'), ['type' => 'submit','class' => 'btn btn-primary m-0','id' => 'editIncomeSave','data-loading-text' => "<span class='spinner-border spinner-border-sm'></span> Processing..."]) }}
            <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal">{{ __('messages.common.cancel') }}</button>
        </div>
        {{ Form::close() }}
    </div>
</div>
</div>
