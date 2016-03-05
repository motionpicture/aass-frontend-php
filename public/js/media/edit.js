$(function(){
    $('form').submit(function(){
        $('p.error').html('');

        var form = $(this).get()[0];
        $.ajax({
            url: '/media/' + $('input[name="rowKey"]', form).val() + '/edit',
            method: 'post',
            dataType: 'json',
            data: new FormData(form),
            processData: false, // Ajaxがdataを整形しない指定
            contentType: false // contentTypeもfalseに指定
        })
        .done(function(data) {
            // エラーメッセー時表示
            if (!data.isSuccess) {
                $('p.error').append(data.messages.join('<br>'));
            } else {
                alert('upload success!');
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
            $('.progress').hide();
        });

        return false;
    });
});
