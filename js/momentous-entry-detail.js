jQuery(document).ready(function ($) {

    $('#momentous-resend-btn').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.prop('disabled')) return;

        if (!confirm('Are you sure you want to resend this entry to Momentus?')) {
            return;
        }

        $btn.prop('disabled', true).text('Resending...');

        $.post(momentousEntryData.ajaxurl, {
            action: 'momentous_manual_resend',
            entry_id: momentousEntryData.entry_id,
            nonce: momentousEntryData.resend_nonce
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('Resend to Momentus');
            }
        }).fail(function () {
            alert('Error: Request failed. Please try again.');
            $btn.prop('disabled', false).text('Resend to Momentus');
        });
    });

    $('#momentous-notify-btn').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.prop('disabled')) return;

        if (!confirm('Send notification email to leads team for manual data entry?')) {
            return;
        }

        $btn.prop('disabled', true).text('Sending...');

        $.post(momentousEntryData.ajaxurl, {
            action: 'momentous_notify_leads',
            entry_id: momentousEntryData.entry_id,
            nonce: momentousEntryData.notify_nonce
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('Notify Leads for Manual Entry');
            }
        }).fail(function () {
            alert('Error: Request failed. Please try again.');
            $btn.prop('disabled', false).text('Notify Leads for Manual Entry');
        });
    });

});
