<form>
    <script src="https://korablobstorage.blob.core.windows.net/modal-bucket/korapay-collections.min.js"></script>
    <button type="button" onclick="payKorapay()"> Pay </button>
</form>


<script>
    function payKorapay() {
        window.Korapay.initialize({
            key: "{{ config('korapay.public_key') }}",
            reference: "your-unique-reference",
            amount: 100,
            currency: "USD",
            customer: {
                name: "John Doe",
                email: "john@doe.com"
            },
            notification_url: "{{ route('korapay.webhook') }}"
        });
    }
</script>
