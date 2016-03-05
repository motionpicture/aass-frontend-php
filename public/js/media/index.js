$(function(){
    // メディアコードごとに更新ボタン
    $('.deleteMedia').on('click', function(e){
        var rootRow = $(this).parent().parent();
        var rowKey = $('input[name="rowKey"]', rootRow).val();

        $.ajax({
            type: 'post',
            url: '/media/' + rowKey + '/delete',
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