(function($) {
    var app = {
        entityField: '.momentous-entity-field-selector',
        entity: {
            accounts: [
                { label: 'Account Code', value: 'AccountCode'},
                { label: 'First Name', value: 'FirstName'},
                { label: 'Last Name', value: 'LastName'},
                { label: 'Email', value: 'Email'},
                { label: 'Company Name', value: 'Company'},
                { label: 'Mobile Number', value: 'Mobile'},
                { label: 'Organization', value: 'Organization'},
                { label: 'Class', value: 'Class'},
            ],
            opportunities: [
                { label: 'Type', value: 'Type'},
                { label: 'Organization', value: 'Organization'},
                { label: 'Class', value: 'Class'},
                { label: 'User Text 01', value: 'UserText01'},
                { label: 'User Text 02', value: 'UserText02'},
                { label: 'User Text 03', value: 'UserText03'},
                { label: 'User Text 04', value: 'UserText04'},
                { label: 'User Text 05', value: 'UserText05'},
                { label: 'User Text 06', value: 'UserText06'},
                { label: 'User Text 07', value: 'UserText07'},
                { label: 'User Text 08', value: 'UserText08'},
                { label: 'User Text 09', value: 'UserText09'},
                { label: 'User Text 10', value: 'UserText10'},
                { label: 'User Text 11', value: 'UserText11'},
                { label: 'User Text 12', value: 'UserText12'},
                { label: 'User Text 13', value: 'UserText13'},
                { label: 'User Text 14', value: 'UserText14'},
                { label: 'User Text 15', value: 'UserText15'},
                { label: 'User Text 16', value: 'UserText16'},
                { label: 'User Text 17', value: 'UserText17'},
                { label: 'User Text 18', value: 'UserText18'},
                { label: 'User Text 19', value: 'UserText19'},
                { label: 'User Text 20', value: 'UserText20'},
                { label: 'User Number 01', value: 'UserNumber01'},
                { label: 'User Number 02', value: 'UserNumber02'},
                { label: 'User Number 03', value: 'UserNumber03'},
                { label: 'User Number 04', value: 'UserNumber04'},
                { label: 'User Number 05', value: 'UserNumber05'},
                { label: 'User Number 06', value: 'UserNumber06'},
                { label: 'User Number 07', value: 'UserNumber07'},
                { label: 'User Number 08', value: 'UserNumber08'},
                { label: 'User Number 09', value: 'UserNumber09'},
                { label: 'User Number 10', value: 'UserNumber10'},
                { label: 'User Number 11', value: 'UserNumber11'},
                { label: 'User Number 12', value: 'UserNumber12'},
                { label: 'User Number 13', value: 'UserNumber13'},
                { label: 'User Number 14', value: 'UserNumber14'},
                { label: 'User Number 15', value: 'UserNumber15'},
            ]
        },
        init: function(config) {

        },
        generateMomentousFields: function (entityType) {
            var fields = this.entity[entityType],
                options = '';
            $(this.entityField).find('option').remove();
            for (var idx= 0; idx < fields.length; idx++) {
                options= options + `<option value="${fields[idx].value}">${fields[idx].label} </option>`;
            }
            $(this.entityField).append(options);
        }
    }
    $(document).ready(function() {
        var entityField = '.momentous-entity-selector';
        var fieldSection = '.field-mapping-section'
        if ($(entityField).val() !=="") {
            $(fieldSection).show();
            app.generateMomentousFields($(entityField).val());
        }
        $(entityField).on('change', function(evt) {
            console.log(this.value);
            if (this.value !== "") {
                $(fieldSection).show();
            } else {
                $(fieldSection).hide();
            }
            app.generateMomentousFields(this.value);
        });
    });
})(jQuery);
