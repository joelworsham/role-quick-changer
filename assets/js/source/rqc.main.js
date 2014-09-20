/**
 * The main plugin script object.
 *
 * @since Role Quick Changer 0.1.0
 */
var RQC;
(function($) {
    RQC = {
        /**
         * Initialize the object.
         *
         * @since Role Quick Changer 0.1.0
         */
        init: function () {

            // Begin the drop-down element
            var dropdown = '<form method="POST"><select id="rqc-dropdown" name="rqc" onchange="submit()">';

            // For each of the existing roles, add an option
            for(i = 0; i < rqc.roles.length; i++) {
                dropdown += '<option value="' + rqc.roles[i].id + '" ' + ( rqc.roles[i].active ? 'selected' : '' ) + '>' + rqc.roles[i].name + '</option>';
            }

            // Close off the drop-down element
            dropdown += '</select></form>';

            // Add the new select-box to the admin bar node
            $('#wp-admin-bar-rqc').html(dropdown);
        }
    };

    // Initialize the object on document.ready
    $(function() {
        RQC.init();
    });
})(jQuery);