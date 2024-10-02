document.addEventListener('turbo:load', loadUserTransactionDate)

function loadUserTransactionDate() {

}

listenClick('#hospitalTransactionsResetFilter', function () {
    $('#paymentType').val('').trigger('change')
    hideDropdownManually($('#hospitalTransactionsFilterButton'),
        $('.dropdown-menu'))
})

listenChange('#paymentType', function () {
    Livewire.dispatch('changeFilter', {statusFilter: $(this).val()})
})

