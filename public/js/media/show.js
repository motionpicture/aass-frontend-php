$(function(){
    // URLコピー
    $('.copy-url a').zclip({
        path: '/js/ZeroClipboard.swf',
        copy: function(){
            var url = $('.url', $(this).parent().parent()).text();
            console.log(url);
            return url;
        },
        afterCopy: function(){
            console.log('copied');
            alertTop('URLをコピーしました');
        },
        beforeCopy: function(){
        }
    });

    $('input[name="media-url"]')
//        .focus(function(){
//            $(this).select();
//            return false;
//        })
        .click(function(){
            $(this).select();
            return false;
    });

    // デフォルトの日時は、本日の00:00:00
    var today = new Date(); 
    var year = today.getFullYear();
    var month = today.getMonth() + 1;
    var day = today.getDate();
    if (month) {
        month = '0' + month;
    }
    if (day < 10) {
        day = '0' + day;
    }
    var defaultDateTime = year + '-' + month + '-' + day + ' 00:00:00';

    $('input[name="start_at"], input[name="end_at"]').datetimepicker({
        format: 'YYYY-MM-DD HH:mm:00',
        pickDate: true,
        pickTime: true,
        useMinutes: true,
        useSeconds: false,
        useCurrent: false, // デフォルトを現在日時にしない
        minuteStepping: 10,
//        minDate: 1/1/1900,
//        maxDate: (today +100 years),
        showToday: true,
        language:'ja', 
        defaultDate: ''
    }).on('dp.show', function(e){
        // 値なしの場合、デフォルト値を指定
        if ($(this).val() == '') {
            $(this).data('DateTimePicker').setValue(defaultDateTime);
            return false;
        }
    });

    $('.update-media-by-code').on('click', function(e){
        var ladda = Ladda.create(this);
        ladda.start();

        var thisBtn = this;
        var rootRow = $(thisBtn).parent().next('.row');
        var mediaCode = $('span.media-code', rootRow).text();
        var movieName = $('input[name="movie_name"]', rootRow).val();
        var startAt = $('input[name="start_at"]', rootRow).val();
        var endAt = $('input[name="end_at"]', rootRow).val();
        var data = {
            movie_name: movieName,
            start_at: startAt,
            end_at: endAt
        };
        console.log('mediaCode: ' + mediaCode);
        console.log(data);

        $.ajax({
            type: 'post',
            url: '/media/' + mediaCode + '/update_by_code',
            data: data,
            dataType: 'json',
            complete: function(response) {
                ladda.stop();
            },
            success: function(response) {
                console.log(JSON.stringify(response));

                if (response.success) {
                    console.log('update media by code success');
                    alertTop('メディアを更新しました');
                } else {
                    console.log('update media by code fail');
                    alertTop('メディアの更新に失敗しました', 'danger');
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log("XMLHttpRequest : " + XMLHttpRequest.status);
                console.log("textStatus : " + textStatus);
                console.log("errorThrown : " + errorThrown.message);
                alertTop('メディアの更新に失敗しました', 'danger');
            }
        });

        return false;
    });

    $('.delete-media').on('click', function(e){
        if (!window.confirm('本当に削除しますか？')) {
            return false;
        }

        var ladda = Ladda.create(this);
        ladda.start();

        var thisBtn = this;
        var rootRow = $(thisBtn).parent().next('.row');
        var mediaId = $('span.media-id', rootRow).text();
        var mediaVersion = $('span.media-version', rootRow).text();
        console.log('mediaId: ' + mediaId);

        $.ajax({
            type: 'post',
            url: '/media/' + mediaId + '/delete',
            data: {},
            dataType: 'json',
            complete: function(response) {
                ladda.stop();
            },
            success: function(response) {
                console.log(JSON.stringify(response));

                if (response.success) {
                    console.log('delete media success');

                    // メディア要素を削除
                    $(thisBtn).parent().prev('h3').remove();
                    $(thisBtn).parent().next('.row').remove();
                    $(thisBtn).parent().remove();

                    alertTop('ver.' + mediaVersion + 'を削除しました');
                } else {
                    console.log('delete media fail');
                    alertTop('ver.' + mediaVersion + 'の削除に失敗しました', 'danger');
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log("XMLHttpRequest : " + XMLHttpRequest.status);
                console.log("textStatus : " + textStatus);
                console.log("errorThrown : " + errorThrown.message);
            }
        });

        return false;
    });
});