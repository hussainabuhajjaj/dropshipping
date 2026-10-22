<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">

<!-- start HEAD -->
<head>
    <meta charset="utf-8"/>
    <title>@yield('title', 'لوحة الدفع') - {{ config('app.name', 'Simbazu') }}</title>

    <!-- Meta Tags -->
    <meta property="og:title" content="@yield('og_title', 'لوحة الدفع')">
    <meta property="og:description" content="@yield('og_description', 'بوابة الدفع الآمنة')">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts - Google Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS (updated to latest stable) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 4.7 (for compatibility) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <!-- Bootstrap Select -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css" rel="stylesheet">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css">

    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Custom CSS -->
    <link href="{{ asset('payment_assets/css/style.css') }}?v={{ time() }}" rel="stylesheet" type="text/css"/>

    <!-- RTL/LTR Specific Overrides -->
    @if(app()->getLocale() == 'ar')
        <style>
            /* RTL specific adjustments for Bootstrap */
            .dropdown-menu-right {
                right: auto;
                left: 0;
            }
            .mr-2 { margin-left: 0.5rem !important; margin-right: 0 !important; }
            .ml-2 { margin-right: 0.5rem !important; margin-left: 0 !important; }
            .mr-3 { margin-left: 1rem !important; margin-right: 0 !important; }
            .ml-3 { margin-right: 1rem !important; margin-left: 0 !important; }
            .text-right { text-align: left !important; }
            .text-left { text-align: right !important; }
        </style>
    @else
        <style>
            /* LTR specific */
            body {
                direction: ltr;
                text-align: left;
            }
            .dropdown-menu {
                text-align: left;
            }
        </style>
    @endif

    <!-- Custom Page Styles -->
    @stack('user_css')

    <style>
        /* Error styles */
        .error {
            color: #dc3545 !important;
            border-color: #dc3545 !important;
        }

        .error:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        /* DataTables custom */
        #m_table_1_length {
            float: left !important;
        }

        #m_table_1_paginate {
            float: right !important;
        }

        .icon-dark:before {
            color: #495057 !important;
        }

        .menu-section {
            margin-top: 15px;
        }

        .menu-section .menu-text {
            color: #48a8a6 !important;
            font-size: 12px;
            font-weight: bold;
            margin: 0 15px;
            margin-bottom: 8px;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Hide audio element */
        #song {
            display: none;
        }
    </style>

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}"/>
</head>

<body>

<!-- Main App Container -->
<div id="app">
    @yield('content')
</div>

<!-- JavaScript Dependencies -->
<!-- jQuery first, then Popper.js, then Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

<!-- Bootstrap Select -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- jQuery UI -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>

<!-- Toastr -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Pace.js (for loading progress) -->
<script src="https://cdn.jsdelivr.net/npm/pace-js@1.2.4/pace.min.js"></script>

<!-- Clipboard.js -->
<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.11/dist/clipboard.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/additional-methods.min.js"></script>

<!-- Animated Modal -->
<script src="https://cdn.jsdelivr.net/npm/animatedmodal@1.0.0/animatedModal.min.js"></script>

<!-- Vite (for Vue/Inertia) - يجب وضعه قبل إغلاق body -->
@vite(['resources/js/app.js'])

<!-- Global Configuration -->
<script>
    // CSRF Token
    window.csrfToken = '{{ csrf_token() }}';
    window.locale = '{{ app()->getLocale() }}';

    // Toastr Configuration
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-left",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut",
        "rtl": {{ app()->getLocale() == 'ar' ? 'true' : 'false' }}
    };

    // jQuery AJAX Setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // jQuery Validation Messages (Arabic)
    $.extend($.validator.messages, {
        required: "هذا الحقل إلزامي",
        remote: "يرجى تصحيح هذا الحقل للمتابعة",
        email: "يجب إدخال عنوان البريد الإلكتروني بالشكل الصحيح",
        url: "رجاء إدخال عنوان موقع إلكتروني صحيح",
        date: "رجاء إدخال تاريخ صحيح",
        dateISO: "رجاء إدخال تاريخ صحيح (ISO)",
        number: "رجاء إدخال عدد بطريقة صحيحة",
        digits: "رجاء إدخال أرقام فقط",
        creditcard: "رجاء إدخال رقم بطاقة ائتمان صحيح",
        equalTo: "رجاء إدخال نفس القيمة",
        extension: "رجاء إدخال ملف بامتداد موافق عليه",
        maxlength: $.validator.format("الحد الأقصى لعدد الحروف هو {0}"),
        minlength: $.validator.format("الحد الأدنى لعدد الحروف هو {0}"),
        rangelength: $.validator.format("عدد الحروف يجب أن يكون بين {0} و {1}"),
        range: $.validator.format("رجاء إدخال عدد قيمته بين {0} و {1}"),
        max: $.validator.format("رجاء إدخال عدد أقل من أو يساوي {0}"),
        min: $.validator.format("رجاء إدخال عدد أكبر من أو يساوي {0}")
    });

    // DataTables Language Configuration
    window.lang = {
        lengthMenu: "@lang('client.show') _MENU_",
        info: "@lang('client.entries_from') _START_ @lang('client.to') _END_ @lang('client.form') _TOTAL_",
        infoEmpty: "@lang('client.entries_from') 0 @lang('client.to') 0 @lang('client.form') 0",
        infoFiltered: "(@lang('filtered_from') _MAX_ @lang('client.from_entries'))",
        processing: "@lang('client.processing')",
        loadingRecords: "@lang('client.loadingRecords')",
        zeroRecords: "@lang('client.not_result')",
        emptyTable: "@lang('client.not_values')",
        paginate: {
            first: "@lang('client.first')",
            previous: "@lang('client.previous')",
            next: "@lang('client.next')",
            last: "@lang('client.last')"
        }
    };

    // Error/Success messages
    window.error_title = "{{ __('messages.error') }}";
    window.error_msg = "{{ __('messages.something_went_wrong') }}";
</script>

<!-- Custom Scripts -->
{{--<script src="{{ asset('client_assets/js/main.js') }}?v={{ time() }}"></script>--}}

<!-- Page Specific Scripts -->
@stack('js')

<!-- RTL/LTR specific adjustments for DataTables -->
@if(app()->getLocale() == 'ar')
    <script>
        // Adjust DataTables for RTL
        $(document).ready(function() {
            if ($.fn.DataTable) {
                $.fn.DataTable.defaults.oLanguage = window.lang;
            }
        });
    </script>
@endif
</body>
</html>
