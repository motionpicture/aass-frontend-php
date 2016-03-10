$(function(){
    // メディアコードごとに更新ボタン
    $('.delete_media').on('click', function(e){
        var rootRow = $(this).parent().parent();
        var id = $('input[name="id"]', rootRow).val();

        $.ajax({
            type: 'post',
            url: '/media/' + id + '/delete',
            data: {},
            dataType: 'json'
        })
        .done(function(data) {
            // エラーメッセー時表示
            if (!data.isSuccess) {
                alert('delete fail!');
            } else {
                rootRow.remove();
                alert('delete success!');
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