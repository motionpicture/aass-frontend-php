$(function(){
    $('form').submit(function(){
        var progressChecker;
        var progressRate = $('#progressRate');
        progressRate.html('0%');
        $('.progress').show();
        $('p.error').html('');

        // 進捗表示
        var name = $('#session_upload_progress_name').val();
        var f = function(){
            $.getJSON('/media/new/progress/' + name, function(data){
                if (data != null) {
                    var rate = Math.round(100 * (data['bytes_processed'] / data['content_length']));
                    progressRate.text(rate + "%");
                }

//                if (data == null || !data['done']) {
//                    progressChecker = setTimeout(f, 500);
//                }
            });
        }
        progressChecker = setInterval(f, 500);

        var isNew = (!$('input[name="id"]', $(this)).val());
        var form = $(this);
        var formElement = form.get()[0];

        $.ajax({
            url: '/media/create',
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
                alert('upload success!');
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
            $('.progress').hide();
            clearTimeout(progressChecker);
        });

        return false;
    });
});
