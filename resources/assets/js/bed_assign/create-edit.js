document.addEventListener('turbo:load', loadBedAssignCreateEdit)

function loadBedAssignCreateEdit () {

    if ($('#createBedAssign').length || $('#editBedAssign').length) {

        const caseIdElement = $('#caseId')
        const BedAssignBedIdElement = $('#BedAssignBedId')
        const ipdPatientIdElement = $('#ipdPatientId')

        if (caseIdElement.length) {
            $('#caseId').select2({
                width: '100%',
            })
            $('#caseId').first().focus()
        }

        if (BedAssignBedIdElement.length) {
            $('#BedAssignBedId').select2({
                width: '100%',
            })
        }

        if (ipdPatientIdElement.length) {
            $('#ipdPatientId').select2({
                placeholder: Lang.get('js.select_ipd_patient'),
                width: '100%',
            })
        }

    } else {

        return false

    }

    $('#BedAssignDate').flatpickr({
        enableTime: true,
        defaultDate: new Date(),
        dateFormat: 'Y-m-d H:i',
        minDate: new Date(),
        locale: $('.userCurrentLanguage').val(),
        onChange: function (selectedDates, dateStr, instance) {
            let minDate = moment($('#assignDate').val()).
                add(1, 'days').
                format()
            if (typeof dischargeFlatPicker != 'undefined') {
                dischargeFlatPicker.set('minDate', minDate)
            }
        },
    })

    let startDate = $('#editBedAssignDate').val()?.split(' ')[0] || [];
    $('#editBedAssignDate').flatpickr({
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        defaultDate: $(this).val(),
        enable: [
            {
                from: startDate,
                to: new Date(),
            },
        ],
        locale: $('.userCurrentLanguage').val(),
        onChange: function (selectedDates, dateStr, instance) {
            let minDate = moment($('#assignDate').val()).
                add(1, 'days').
                format()
            if (typeof dischargeFlatPicker != 'undefined') {
                dischargeFlatPicker.set('minDate', minDate)
            }
        },
    })

    if ($('#editBedAssign').length) {
        setTimeout(function () {
            $('#caseId').trigger('change')
            $('#BedAssignDate').trigger('dp.change')
        }, 300)

        let dischargeFlatPicker = $('#BedAssignDischargeDate').flatpickr({
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            locale: $('.userCurrentLanguage').val(),
        })

        let minDate = moment($('#BedAssignDate').val()).add(1, 'days').format()

        dischargeFlatPicker.set('minDate', minDate)

    }

    let dischargeFlatPicker = undefined

}

listenSubmit('#createBedAssign, #editBedAssign', function () {
    $('#saveBtn').attr('disabled', true)
    if ($('#validationErrorsBox').text() !== '') {
        $('#BedAssignSaveBtn').attr('disabled', false)
    }
})

listenChange('#caseId', function () {
    if ($(this).val() !== '') {
        $.ajax({
            url: $('#ipdPatientListUrl').val(),
            type: 'get',
            dataType: 'json',
            data: { id: $(this).val() },
            success: function (data) {
                if (data.data.length !== 0) {
                    $('#ipdPatientId').empty()
                    $('#ipdPatientId').removeAttr('disabled')
                    $.each(data.data, function (i, v) {
                        $('#ipdPatientId').
                            append($('<option></option>').
                                attr('value', i).
                                text(v))
                    })
                } else {
                    $('#ipdPatientId').prop('disabled', true)
                }
            },
        })
    }
    $('#ipdPatientId').empty()
    $('#ipdPatientId').append('<option>'+Lang.get('js.no_ipd_patient_found')+'</option>')
    $('#ipdPatientId').prop('disabled', true)
})
