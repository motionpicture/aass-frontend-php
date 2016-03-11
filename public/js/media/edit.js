$(function(){
    $('form').submit(function(){
        var form = $(this);
        var formElement = form.get()[0];
        var isNew = (!$('input[name="id"]', form).val());

        var progressText = $('#progressText');
        var progressRate = $('#progressRate');
        progressRate.html('0%');
        $('.progress').show();

        if (isNew) {
            var file = $('input[name="file"]', form)[0].files[0];
            var f = file.name.split('.');
            var extension = f[f.length-1];
            var size = file.size;
            var assetId = null;
            var filename = null;
            var eof = false;
            var chunkSize = 2048 * 2048; // byte
            var counter = 0;
            var index = 0;
            var division = Math.ceil(size / chunkSize);
        }

        $('p.error').html('');

        var loadFile = function()
        {
        	var readPos = chunkSize * index;
            var endPos = readPos + chunkSize;
            if (endPos > size) {
                endPos = size;
                eof = true;
            }

            var blob;
            // chunk可能なAPIを保持しているかチェック
            if (file.slice) {
                blob = file.slice(readPos, endPos);
            } else if(file.webkitSlice) {
                blob = file.webkitSlice(readPos, endPos);
            } else if (file.mozSlice) {
                blob = file.mozSlice(readPos, endPos);
            } else {
                alert('The File APIs are not Slice supported in this browser.');
                return false;
            }

            // ファイルの分割開始
            var fileReader = new FileReader();

            // ファイル読み込み後のイベント処理にて、アップロード要求を実施する。
            fileReader.onloadend = function(e)
            {
                // ステータスチェック
                if (e.target.readyState == FileReader.DONE) { // DONE == 2
                    console.log('onloadend readPos:' + readPos);
                    console.log('onloadend endPos:' + endPos);
                    sendFile(e.target.result);
                }
            } 

            fileReader.readAsDataURL(blob);
        }

        var sendFile = function(fileData)
        {
            progressText.append('<br>' + (index+1) + '/' + division + 'をアップロードしています...');
            var formData = new FormData();
            formData.append('file', fileData);
            formData.append('extension', extension);
            formData.append('size', size);
            formData.append('assetId', assetId);
            formData.append('filename', filename);
            formData.append('counter', counter);
            formData.append('eof', Number(eof));

            $.ajax({
                url: '/media/appendFile',
                method: 'post',
                dataType: 'json',
                data: formData,
                processData: false, // Ajaxがdataを整形しない指定
                contentType: false // contentTypeもfalseに指定
            })
            .done(function(data) {
                // エラーメッセー時表示
                if (!data.isSuccess) {
                    $('p.error').append(data.messages.join('<br>'));
                } else {
                    // フォームを空に
                    if (isNew) {
                        if (eof) {
                            progressText.append('<br>ファイルアップロード完了');
                            // DB登録
                            createMedia();
                        } else {
                            // 次のファイルパーツへ
                            counter = data.params.counter;
                            index++;
                            loadFile();
                        }
                    }
                }
            })
            .fail(function() {
                alert('fail');
            })
            .always(function() {
            });
        }

        var createAsset = function()
        {
            progressText.append('<br>アセットを作成しています...');

            $.ajax({
                url: '/media/createAsset',
                method: 'post',
                dataType: 'json',
                data: {},
                processData: false, // Ajaxがdataを整形しない指定
                contentType: false // contentTypeもfalseに指定
            })
            .done(function(data) {
                // エラーメッセー時表示
                if (!data.isSuccess) {
                    $('p.error').append(data.messages.join('<br>'));
                } else {
                    // アセットIDとファイル名を取得
                    assetId = data.params.assetId;
                    filename = data.params.filename;
                    loadFile();
                }
            })
            .fail(function() {
                alert('fail');
            })
            .always(function() {
            });
        }

        var createMedia = function()
        {
            progressText.append('<br>DBに登録します...');
            var formData = new FormData(formElement);

            if (isNew) {
                formData.append('extension', extension);
                formData.append('size', size);
                formData.append('assetId', assetId);
                formData.append('filename', filename);
            }

            $.ajax({
                url: '/media/create',
                method: 'post',
                dataType: 'json',
                data: formData,
                processData: false, // Ajaxがdataを整形しない指定
                contentType: false // contentTypeもfalseに指定
            })
            .done(function(data) {
                // エラーメッセー時表示
                if (!data.isSuccess) {
                    $('p.error').append(data.messages.join('<br>'));
                } else {
                    // フォームを空に
                    if (isNew) {
                        $('input,textarea', form).val('');
                    }
                    progressText.append('<br>登録完了');
                }
            })
            .fail(function() {
                alert('fail');
            })
            .always(function() {
//                if (isNew) {
//                    $('.progress').hide();
//                    clearTimeout(progressChecker);
//                }
            });
        }

        if (isNew) {
            createAsset();
        } else {
            createMedia();
        }

        return false;
    });
});
