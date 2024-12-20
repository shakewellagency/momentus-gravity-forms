(function($) {
    var app = {
        init: function(config) {

        }
    }
   $(document).ready(function() {
        var entityField = '.momentous-entity-selector';
        var fieldSection = '.field-mapping-section'
        $(entityField).on('change', function(evt) {
            console.log(this.value);
            if (this.value !== "") {
                $(fieldSection).show();
            } else {
                $(fieldSection).hide();
            }
        });
   });
})(jQuery);
