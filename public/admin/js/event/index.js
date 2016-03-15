$(function(){
    $('.accept_application').on('click', function(e){
        var rootRow = $(this).parent().parent();
        var applicationId = $('input[name="application_id"]', rootRow).val();

        $.ajax({
            type: 'post',
            url: '/admin/application/' + applicationId + '/accept',
            data: {},
            dataType: 'json'
        })
        .done(function(data) {
            // エラーメッセー時表示
            if (!data.isSuccess) {
                alert('delete fail!');
            } else {
                $('.application_status', rootRow).html('申請承認済み');
                alert('承認しました');
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
        });;

        return false;
    });

    $('.reject_application').on('click', function(e){
        var rootRow = $(this).parent().parent();
        var applicationId = $('input[name="application_id"]', rootRow).val();

        $.ajax({
            type: 'post',
            url: '/admin/application/' + applicationId + '/reject',
            data: {},
            dataType: 'json'
        })
        .done(function(data) {
            // エラーメッセー時表示
            if (!data.isSuccess) {
                alert('delete fail!');
            } else {
                $('.application_status', rootRow).html('申請却下済み');
                alert('却下しました');
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
        });;

        return false;
    });

    $('.delete_application').on('click', function(e){
        var rootRow = $(this).parent().parent();
        var applicationId = $('input[name="application_id"]', rootRow).val();

        $.ajax({
            type: 'post',
            url: '/admin/application/' + applicationId + '/delete',
            data: {},
            dataType: 'json'
        })
        .done(function(data) {
            // エラーメッセー時表示
            if (!data.isSuccess) {
                alert('delete fail!');
            } else {
                $('.download_application', rootRow).remove();
                $('.delete_application', rootRow).remove();
                $('.media_detail', rootRow).html('');
                $('.application_status', rootRow).html('動画待ち');
                alert('削除しました');
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
        });;

        return false;
    });
});