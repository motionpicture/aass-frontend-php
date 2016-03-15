var MediaEdit = {
    isNew: false,
    file: null,
    extension: null,
    size: null,
    assetId: null,
    filename: null,
    chunkSize: 2048 * 2048, // byte
    division: null,
    createBlobBlockTimer: null,
    blobBlockUncreatedIndexes: [], // 未作成ブロックインデックス
    blobBlockCreatedIndexes: [], // 作成済みブロックインデックス
    blobBlockCreatingIndexes: [], // 作成中ブロックインデックス

    initialize: function()
    {
        this.isNew = (!$('input[name="id"]', $('form')).val());

        if (this.isNew) {
            this.file = $('input[name="file"]', $('form'))[0].files[0];
            console.log(this.file);
            f = this.file.name.split('.');
            this.extension = f[f.length-1];
            this.size = this.file.size;
            this.division = Math.ceil(this.size / this.chunkSize);
            console.log(this.file, this.extension, this.size);
            for (var i=0; i<this.division; i++) {
                this.blobBlockUncreatedIndexes.push(i);
            }
        }
    },

    showProgress: function(text)
    {
        $('#progressText').html(text);
    },

    loadFile: function(context, blockIndex)
    {
        var self = context;

        var readPos = self.chunkSize * blockIndex;
        var endPos = readPos + self.chunkSize;
        if (endPos > self.size) {
            endPos = self.size;
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
                self.createBlobBlock(e.target.result, blockIndex);
            }
        }

        fileReader.readAsDataURL(blob);
    },

    createBlobBlock: function(fileData, blockIndex)
    {
        var self = this;

        var formData = new FormData();
        formData.append('file', fileData);
        formData.append('extension', self.extension);
        formData.append('assetId', self.assetId);
        formData.append('filename', self.filename);
        formData.append('index', blockIndex);

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
//                $('p.error').append(data.messages.join('<br>'));
                // リトライ
                self.blobBlockUncreatedIndexes.push(blockIndex);
                self.blobBlockCreatingIndexes.splice(self.blobBlockCreatingIndexes.indexOf(blockIndex), 1);
            } else {
                // 結果保存
                console.log('created. index:' + blockIndex);
                self.blobBlockCreatedIndexes.push(blockIndex);
                self.blobBlockCreatingIndexes.splice(self.blobBlockCreatingIndexes.indexOf(blockIndex), 1);

                var blobBlockCreatedCount = self.blobBlockCreatedIndexes.length;
                console.log('blobBlockCreatedCount:' + blobBlockCreatedCount);

                var rate = Math.floor(blobBlockCreatedCount * 100 / self.division);
                self.showProgress(rate + '% (' + blobBlockCreatedCount + '/' + self.division + ') をアップロードしました...');

                // ブロブブロックを全て作成したらコミット
                if (blobBlockCreatedCount == self.division) {
                    // コミット
                    self.commitFile();
                }
            }
        })
        .fail(function() {
            // リトライ
            self.blobBlockUncreatedIndexes.push(blockIndex);
            self.blobBlockCreatingIndexes.splice(self.blobBlockCreatingIndexes.indexOf(blockIndex), 1);

            // 3度までリトライ?
//            if (tryCount < 3) {
//                self.loadFile(self, blockIndex, tryCount + 1);
//            } else {
//                // タイマークリア
//                clearInterval(self.createBlobBlockTimer);
//                self.createBlobBlockTimer = null;
//                alert('ブロブブロックを作成できませんでした blockIndex:' + blockIndex);
//            }
        })
        .always(function() {
        });
    },

    commitFile: function()
    {
        var self = this;
        self.showProgress('ブロブブロックをコミットします...');

        var formData = new FormData();
        formData.append('extension', self.extension);
        formData.append('assetId', self.assetId);
        formData.append('filename', self.filename);
        formData.append('blockCount', self.division);

        $.ajax({
            url: '/media/commitFile',
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
                self.showProgress('ファイルアップロード完了');

                // DB登録
                self.createMedia();
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

                // 定期的にブロブブロック作成
                self.createBlobBlockTimer = setInterval(function()
                {
                    // 回線が遅い場合、アクセスがたまりすぎないように調整
                    if (self.blobBlockCreatingIndexes.length > 10) {
                        return;
                    }

                    if (self.blobBlockUncreatedIndexes.length > 0) {
                        var nextIndex = self.blobBlockUncreatedIndexes[0];
                        self.blobBlockCreatingIndexes.push(nextIndex);
                        self.blobBlockUncreatedIndexes.shift();
                        self.loadFile(self, nextIndex);
                    } else {
                        clearInterval(self.createBlobBlockTimer);
                    }
                }, 50);
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
