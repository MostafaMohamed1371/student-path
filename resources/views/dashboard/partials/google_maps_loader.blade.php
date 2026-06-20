@php
    $googleMapsApiKey = (string) config('google.maps_api_key');
    $googleMapsLanguage = app()->getLocale();
    $googleMapsRegion = (string) config('google.places_region', 'iq');
@endphp
@if($googleMapsApiKey !== '')
<script>
(function () {
    if (window.ensureGoogleMapsLoaded) {
        return;
    }

    window.__googleMapsInitQueue = window.__googleMapsInitQueue || [];

    window.initGoogleMapsApi = function () {
        const queue = window.__googleMapsInitQueue || [];
        window.__googleMapsInitQueue = [];
        queue.forEach(function (callback) {
            try {
                callback();
            } catch (error) {
                console.error(error);
            }
        });
    };

    window.gm_authFailure = function () {
        document.dispatchEvent(new CustomEvent('google-maps-auth-failure'));
    };

    window.ensureGoogleMapsLoaded = function (callback) {
        if (window.google && window.google.maps) {
            callback();
            return;
        }

        window.__googleMapsInitQueue.push(callback);

        if (document.getElementById('google-maps-js')) {
            return;
        }

        const script = document.createElement('script');
        script.id = 'google-maps-js';
        script.async = true;
        script.defer = true;
        script.src = @json(
            'https://maps.googleapis.com/maps/api/js?key='.$googleMapsApiKey
            .'&language='.$googleMapsLanguage
            .'&region='.$googleMapsRegion
            .'&callback=initGoogleMapsApi'
        );
        document.head.appendChild(script);
    };
})();
</script>
@endif
