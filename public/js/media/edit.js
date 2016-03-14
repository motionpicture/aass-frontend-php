var MediaEdit = {
    isNew: false,
    file: null,
    extension: null,
    size: null,
    assetId: null,
    filename: null,
    eof: false,
    chunkSize: 2048 * 2048, // byte
    counter: 0,
    index: 0,

    initialize: function()
    {
        this.isNew = (!$('input[name="id"]', $('form')).val());

        if (this.isNew) {
            this.file = $('input[name="file"]', $('form'))[0].files[0];
            console.log(this.file);
            f = this.file.name.split('.');
            this.extension = f[f.length-1];
            this.size = this.file.size;
            console.log(this.file, this.extension, this.size);
        }
    },

    showProgress: function(text)
    {
        $('#progressText').html(text);
    },

    loadFile: function()
    {
        var self = this;
        var readPos = self.chunkSize * self.index;
        var endPos = readPos + self.chunkSize;
        if (endPos > self.size) {
            endPos = self.size;
            self.eof = true;
        }

        var blob;
        // chunk可能なAPIを保持しているかチェック
        if (self.file.slice) {
            blob = self.file.slice(readPos, endPos);
        } else if(self.file.webkitSlice) {
            blob = self.file.webkitSlice(readPos, endPos);
        } else if (self.file.mozSlice) {
            blob = self.file.mozSlice(readPos, endPos);
        } else {
            alert('The File APIs are not Slice supported in this browser.');
            return false;
        }

        // ファイルの分割開始
        var fileReader = new FileReader();

        // ファイル読み込み後アップロード
        fileReader.onloadend = function(e)
        {
            // ステータスチェック
            if (e.target.readyState == FileReader.DONE) { // DONE == 2
                self.onFileReaderLoadend(e.target.result);
            }
        }

        fileReader.readAsDataURL(blob);
    },

    onFileReaderLoadend: function(fileData)
    {
        var self = this;
        var division = Math.ceil(self.size / self.chunkSize);
        self.showProgress((self.index + 1) + '/' + division + 'をアップロードしています...');

        var formData = new FormData();
        formData.append('file', fileData);
        formData.append('extension', self.extension);
        formData.append('size', self.size);
        formData.append('assetId', self.assetId);
        formData.append('filename', self.filename);
        formData.append('counter', self.counter);
        formData.append('eof', Number(self.eof));

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
                if (self.eof) {
                    self.showProgress('ファイルアップロード完了');

                    // DB登録
                    self.createMedia();
                } else {
                    console.log('conuter:' + data.params.counter);
                    // 次のファイルパーツへ
                    self.counter = data.params.counter;
                    self.index++;
                    self.loadFile();
                }
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
        });
    },

    createAsset: function()
    {
        var self = this;
        self.showProgress('ブロブを準備しています...');

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
                console.log(data.params);
                self.assetId = data.params.assetId;
                self.filename = data.params.filename;
                self.loadFile();
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
        });
    },

    createMedia: function()
    {
        var self = this;

        self.showProgress('DBに登録します...');
        var formData = new FormData();
        formData.append('id', $('input[name="id"]', $('form')).val());
        formData.append('title', $('input[name="title"]', $('form')).val());
        formData.append('description', $('textarea[name="description"]', $('form')).val());
        formData.append('uploadedBy', $('input[name="uploadedBy"]', $('form')).val());
        formData.append('extension', self.extension);
        formData.append('size', self.size);
        formData.append('assetId', self.assetId);
        formData.append('filename', self.filename);

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
                if (self.isNew) {
                    $('input,textarea', $('form')).val('');
                }
                self.showProgress('登録完了');
            }
        })
        .fail(function() {
            alert('fail');
        })
        .always(function() {
//            if (self.isNew) {
//                $('.progress').hide();
//            }
        });
    }
}

$(function(){
    $(document).on('click', 'form button', function(){
        $('.progress').show();
        $('p.error').html('');

        MediaEdit.initialize();

        if (MediaEdit.isNew) {
            MediaEdit.createAsset();
        } else {
            MediaEdit.createMedia();
        }
    });
});
