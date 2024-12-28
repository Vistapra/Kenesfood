$("#checkAll").click(function(){
    $('input:checkbox').not(this).prop('checked', this.checked);
});

if ($('.datepicker').length > 0) {
    $('.datepicker').datepicker({
        todayHighlight: true,
        autoclose: true,
        changeMonth: true,
        changeYear: true,
        format: 'yyyy-mm-dd',
    });
} 

if ($('.datetimepicker').length > 0) {
    // Permit time
    new tempusDominus.TempusDominus(document.getElementById('permit_time'),
    {
        allowInputToggle: true,
        container: undefined,
        dateRange: false,
        debug: false,
        defaultDate: undefined,
        localization: {
            format: 'yyyy-MM-dd HH:mm',
        },
        display: {
            icons: {
                type: 'icons',
                time: 'fa-regular fa-clock',
                date: 'fa-regular fa-calendar',
                up: 'fa-solid fa-arrow-up',
                down: 'fa-solid fa-arrow-down',
                previous: 'fa-solid fa-chevron-left',
                next: 'fa-solid fa-chevron-right'
            },
            sideBySide: true,
            calendarWeeks: false,
            viewMode: 'calendar',
            toolbarPlacement: 'bottom',
            keepOpen: false,
            buttons: {
                today: false,
                clear: false,
                close: false
            },
            components: {
                calendar: true,
                date: true,
                month: true,
                year: true,
                decades: true,
                clock: true,
                hours: true,
                minutes: true,
                seconds: false,
                useTwentyfourHour: true
            },
            inline: false,
            theme: 'light'
        },
        keepInvalid: false,
        meta: {},
        multipleDates: false,
        multipleDatesSeparator: '; ',
        promptTimeOnDateChange: false,
        promptTimeOnDateChangeTransitionDelay: 200,
        restrictions: {
            minDate: undefined,
            maxDate: undefined,
            disabledDates: [],
            enabledDates: [],
            daysOfWeekDisabled: [],
            disabledTimeIntervals: [],
            disabledHours: [],
            enabledHours: []
        },
        stepping: 1,
        useCurrent: true
    });
}

if ($('.select2').length > 0) {
    $('.select2').select2({
        allowClear: false,
        placeholder: 'Pilih Salah Satu',
        width: 'resolve'
    });
}

if ($('.select2-multiple').length > 0) {
    $('.select2-multiple').select2({
        multiple: true,
        allowClear: false,
        placeholder: 'Pilih Salah Satu',
        width: 'resolve'
    });
}

if ($('.form-check-input').length > 0) {
    $('body').on('change', '.form-check-input', function(e) {
        var jabatan = $('input[name="position"]:checked').val();
        if(jabatan == 1) {
            $('.jabatan').removeAttr('hidden');
            $('#position_code').attr('required', 'true');
            $('#position_name').attr('required', 'true');
        } else {
            $('.jabatan').attr('hidden', 'true');
            $('#position_code').removeAttr('required');
            $('#position_name').removeAttr('required');
        }
    });
}

if ($('#parent_id').length > 0) {
    var nav_id = $('#nav_id').val();
    if(nav_id == "") {
        get_nav_id();
    }
    $('body').on('change', '#parent_id', function(e) {
        get_nav_id();
    });
}

function get_nav_id() {
    var parent_id = $('#parent_id').val();
    var url = $('#url').val();
    $.ajax({
        type: "GET",
        dataType: "json",
        url: url,
        data: { parent_id: parent_id },
    })
    .done(function(data) {
        $('#nav_id').val(data.nav_id);
        $('#nav_no').val(data.nav_no);
    })
    .fail(function() {
        console.log('Failed');
    });
}

function confirm_delete(url, e) {
    e.preventDefault();
    Swal.fire({
        title: "Hapus data",
        text: 'Anda yakin akan menghapus data tersebut?',
        icon: "question",
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: 'Ya',
        denyButtonText: 'Tidak',
        customClass: {
          actions: 'my-actions',
          cancelButton: 'order-1 right-gap',
          confirmButton: 'order-2',
          denyButton: 'order-3',
        },
      }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        } else if (result.isDenied) {
            Swal.fire('Data batal dihapus');
        }
      })
}