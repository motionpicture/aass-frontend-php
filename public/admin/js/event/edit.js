$(function(){
	$('input[name="held"]').daterangepicker({
        timePicker: true,
        timePicker24Hour: true,
        timePickerIncrement: 10,
        startDate: $('form input[name="held_from"]').val(),
        endDate: $('form input[name="held_to"]').val(),
        locale: {
            format: 'YYYY/MM/DD HH:mm',
//            "separator": " - ",
            "applyLabel": "決定",
            "cancelLabel": "キャンセル",
//            "fromLabel": "From",
//            "toLabel": "To",
//            "customRangeLabel": "Custom",
        }
    }, function(start, end, label)
    {
    	$('form input[name="held_from"]').val(start.format('YYYY-MM-DD HH:mm:ss'));
    	$('form input[name="held_to"]').val(end.format('YYYY-MM-DD HH:mm:ss'));
    });

    $('form').submit(function(){
        $('p.error').html('');
        var isNew = (!$('input[name="id"]', $(this)).val());
        var form = $(this);
        var formElement = form.get()[0];

        $.ajax({
            url: '/admin/event/update',
            method: 'post',
            dataType: 'json',
            data: new FormData(formElement),
            processData: false, // Ajaxがdataを整形しない指定
            contentType: false // contentTypeもfalseに指定
        })
        .done(function(data) {
            // エラーメッセー時表示
            if (!data.isSuccess) {
                $('p.error').append(data.messages.join('<br>'));
            } else {
                alert('create success!');
                // フォームを空に
                if (isNew) {
                    $('input', form).val('');
                }
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
        });

        return false;
    });
});
