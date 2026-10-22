@extends('payment_webview.layout')

@push('user_css')
    <style>
        /* تخصيصات إضافية للصفحة فقط */
        .pay-page-portlet {
            margin: 30px 0 !important;
        }

        /* تحسين مظهر loader */
        .loader-container {
            display: none;
        }

        /* تنسيق خاص للجوال */
        @media (max-width: 576px) {
            .pay-card-head {
                padding: 15px !important;
            }

            .pay-card-body {
                padding: 15px !important;
            }

            .summary-side-card {
                padding: 20px !important;
            }
        }
    </style>
@endpush

@section('content')
    <div id="app">
{{--            token="{{ $token ?? '' }}"--}}
{{--            class-type="{{ $classType ?? '' }}"--}}
{{--            id="{{ $id ?? '' }}"--}}
{{--            :price="{{ $price ?? 0 }}"--}}
{{--            :order-items="{{ json_encode($orderItems ?? []) }}"--}}
{{--            :locales="{{ json_encode($locales ?? ['ar', 'en']) }}"--}}
{{--            current-locale="{{ app()->getLocale() }}"--}}
{{--            :errors="{{ json_encode($errors ?? []) }}"--}}
{{--            success-message="{{ session('success') ?? '' }}"--}}
{{--            error-message="{{ session('error') ?? '' }}"--}}
        <PaymentLayout/>
    </div>
@endsection

@push('js')
{{--    <script>--}}
{{--        $(document).ready(function() {--}}
{{--            // تهيئة DataTables إذا وجدت--}}
{{--            if ($('.d-table').length) {--}}
{{--                $('.d-table').DataTable({--}}
{{--                    ordering: false,--}}
{{--                    searching: false,--}}
{{--                    paging: false,--}}
{{--                    info: false,--}}
{{--                    responsive: true,--}}
{{--                    language: window.lang--}}
{{--                });--}}
{{--            }--}}

{{--            // تهيئة Select2--}}
{{--            $('.select2').select2({--}}
{{--                theme: 'bootstrap4',--}}
{{--                language: 'ar',--}}
{{--                dir: 'rtl'--}}
{{--            });--}}

{{--            // تهيئة Bootstrap Select--}}
{{--            $('.selectpicker').selectpicker();--}}

{{--            // التحقق من وجود رسالة خطأ في session--}}
{{--            @if(session('error'))--}}
{{--            toastr.error("{{ session('error') }}");--}}
{{--            @endif--}}

{{--            @if(session('success'))--}}
{{--            toastr.success("{{ session('success') }}");--}}
{{--            @endif--}}
{{--        });--}}

{{--        // دالة إظهار/إخفاء loader--}}
{{--        function showHideLoader(attrVal) {--}}
{{--            $('.loader-container').css('display', '' + attrVal);--}}
{{--        }--}}

{{--        // نسخ النصوص (للبنوك)--}}
{{--        if (ClipboardJS) {--}}
{{--            var clipboard = new ClipboardJS('.copy-bank_account');--}}
{{--            clipboard.on('success', function(e) {--}}
{{--                toastr.success('تم النسخ بنجاح');--}}
{{--                e.clearSelection();--}}
{{--            });--}}
{{--        }--}}

{{--        // تشغيل صوت الإشعار--}}
{{--        function playNotification() {--}}
{{--            var song = document.getElementById('song');--}}
{{--            if (song) {--}}
{{--                song.play().catch(function(error) {--}}
{{--                    console.log('Auto-play was prevented');--}}
{{--                });--}}
{{--            }--}}
{{--        }--}}
{{--    </script>--}}
    @vite(['resources/js/components/Payments/app.js'])
@endpush
