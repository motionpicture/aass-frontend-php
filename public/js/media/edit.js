var MediaEdit = {
    isNew: false,
    file: null,
    extension: null,
    size: null,
    assetId: null,
    container: null,
    filename: null,
    chunkSize: 4194304, // byte
    division: null,
    createBlobBlockTimer: null,
    blobBlockMaxSize: 4194304, // byte
    blobBlockUncreatedIndexes: [], // 未作成ブロックインデックス
    blobBlockCreatedIndexes: [], // 作成済みブロックインデックス
    blobBlockCreatingIndexes: [], // 作成中ブロックインデックス
    blobBlockCreatingAjaxes: {}, // ブロック作成ajaxリスト
    messages: [],

    validate: function()
    {
        if (!$('input[name="title"]', $('form')).val()) {
            this.messages.push('動画名を入力してください');
        }
        if (!$('textarea[name="description"]', $('form')).val()) {
            this.messages.push('動画概要を入力してください');
        }
        if (!$('input[name="uploaded_by"]', $('form')).val()) {
            this.messages.push('動画登録者名を入力してください');
        }
        if ($('input[name="file"]', $('form'))[0].files.length == 0) {
            this.messages.push('ファイルを選択してください');
        }

        return (this.messages.length < 1);
    },

    initialize: function()
    {
        var self = this;

        this.isNew = (!$('input[name="id"]', $('form')).val());
        this.assetId = null;
        this.container = null;
        this.filename = null;
        this.chunkSize = parseInt($('select[name="chunk_size"]', $('form')).val());
        this.division = null;
        this.createBlobBlockTimer = null;
        this.blobBlockMaxSize = parseInt($('input[name="max_block_size"]', $('form')).val());
        this.blobBlockUncreatedIndexes = [];
        this.blobBlockCreatedIndexes = [];
        this.blobBlockCreatingIndexes = [];
        this.blobBlockCreatingAjaxes = [];
        this.messages = [];

        $('#progressModal').on('hidden.bs.modal', function (e) {
            self.cancelUpload(self);
        });

    },

    cancelUpload: function(context)
    {
        var self = context;

        if (self.createBlobBlockTimer !== null) {
            console.log('canceling upload...');
            alert('キャンセルしました');
            clearInterval(self.createBlobBlockTimer);
            $.each(self.blobBlockCreatingAjaxes.keys, function(key, ajax){
                self.blobBlockCreatingAjaxes[key].abort();
            });
        }
    },

    showProgress: function(text)
    {
        $('#progressModal .modal-body p').html(text);
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

        self.createBlobBlock(blob, blockIndex);
    },

    createBlobBlock: function(fileData, blockIndex)
    {
        var self = this;

        var formData = new FormData();
        formData.append('file', fileData);
        formData.append('extension', self.extension);
        formData.append('container', self.container);
        formData.append('filename', self.filename);

        var startIndex = blockIndex * Math.floor(self.chunkSize / self.blobBlockMaxSize);
        formData.append('index', startIndex);

        var ajax = $.ajax({
            url: '/media/appendFile',
            method: 'post',
//            timeout: 25000,
            dataType: 'json',
            data: formData,
            processData: false, // Ajaxがdataを整形しない指定
            contentType: false // contentTypeもfalseに指定
        })
        .done(function(data) {
            // エラーメッセー時表示
            if (!data.isSuccess) {
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
                    clearInterval(self.createBlobBlockTimer);
                    self.createBlobBlockTimer = null;

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
            // ajaxリストから削除
            delete self.blobBlockCreatingAjaxes[blockIndex];
        });

        // ajaxリストに追加
        self.blobBlockCreatingAjaxes[blockIndex] = ajax;
    },

    commitFile: function()
    {
        var self = this;
        self.showProgress('ブロブブロックをコミットします...');

        var formData = new FormData();
        formData.append('extension', self.extension);
        formData.append('asset_id', self.assetId);
        formData.append('container', self.container);
        formData.append('filename', self.filename);
        var blockCount = Math.ceil(self.size / self.blobBlockMaxSize);
        formData.append('blockCount', blockCount);

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

        this.file = $('input[name="file"]', $('form'))[0].files[0];
        f = this.file.name.split('.');
        this.extension = f[f.length-1];
        this.size = parseInt(this.file.size);
        this.division = Math.ceil(this.size / this.chunkSize);
        console.log(this.file, this.extension, this.size, this.division);
        for (var i=0; i<this.division; i++) {
            this.blobBlockUncreatedIndexes.push(i);
        }

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
                self.container = data.params.container;
                self.filename = data.params.filename;

                // 定期的にブロブブロック作成
                self.showProgress(self.chunkSize + 'byteごとに分割アップロードします...');
                self.createBlobBlockTimer = setInterval(function()
                {
                    // 回線が遅い場合、アクセスがたまりすぎないように調整(ブラウザ同時接続数を考慮)
                    if (self.blobBlockCreatingIndexes.length > 0) {
                        return;
                    }

                    if (self.blobBlockUncreatedIndexes.length > 0) {
                        var nextIndex = self.blobBlockUncreatedIndexes[0];
                    	console.log('nextIndex:' + nextIndex);
                        self.blobBlockCreatingIndexes.push(nextIndex);
                        self.blobBlockUncreatedIndexes.shift();
                        self.loadFile(self, nextIndex);
                    }
                }, 300);
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
        formData.append('uploaded_by', $('input[name="uploaded_by"]', $('form')).val());
        formData.append('extension', self.extension);
        formData.append('size', self.size);
        formData.append('asset_id', self.assetId);
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
                    $('input[name="title"]', $('form')).val('');
                    $('textarea[name="description"]', $('form')).val('');
                    $('input[name="uploaded_by"]', $('form')).val('');
                    $('input[name="file"]', $('form')).val('');
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
        $('p.error').html('');
        MediaEdit.initialize();

        if (MediaEdit.validate()) {
            if (MediaEdit.isNew) {
                $('#progressModal').modal('show');
                MediaEdit.createAsset();
            } else {
                MediaEdit.createMedia();
            }
        } else {
            $('p.error').append(MediaEdit.messages.join('<br>'));
        }
    });
});
