<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <script src="html5upload.js"></script>
    <script src="jquery-2.0.3.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
</head>
<body>

    <div class="row">
        <div class="col-lg-6 col-lg-offset-3">
            <form id="upload-form" class="form-inline">
                <fieldset>
                    <legend>Upload example</legend>
                    <input id="upload-file" class="form-control" name="file" type="file" style="width:400px;">
                    <button id="upload-submit" class="btn btn-success" type="button">Upload</button>
                    <button id="upload-cancel" class="btn btn-danger" type="cancel">Cancel</button>
                </fieldset>
            </form>
            <div class="progress progress-striped active" style="margin: 10px 0;">
                <div id="upload-progress" class="progress-bar" style="width: 0"></div>
            </div>
            <br>
            <div id="upload-message">
                <h4></h4>
                <p></p>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function () {
            var $form = $('#upload-form');
            var message = {
                element: $('#upload-message')[0],
                show: function(type, title, text) {
                    this.element.className = 'alert alert-block alert-' + type;
                    $(this.element).find('h4').html(title);
                    $(this.element).find('p').html(text);
                    $(this.element).show();
                },
                hide: function() {
                    $(this.element).hide();
                }
            };

            if (!html5upload.Upload.support()) {
                message.show(
                        'error',
                        'Error',
                        'Your browser is crap'
                        );
                $form.hide();
            } else {
                message.hide();
                $form.show();
                $form.find('#upload-submit').on('click', function() {
                    var file = $form.find('#upload-file')[0].files[0];
                    var chunkSize = 1048576; // 1MB
                    if (file) {
                        message.hide();
                        var upload = new html5upload.Upload({
                            url: '/upload/',
                            file: file,
                            chunkSize: chunkSize
                        }, {
                            onstart: function(file) {
                                $('#upload-submit').attr('disabled', 'disabled');
                                $('#upload-cancel').removeAttr('disabled');
                            },
                            onchunk: function(file, index, bytes, percents) {
                                console.log(index, (bytes / 1024 / 1024).toFixed(2), Math.floor(percents));
                                $('#upload-progress').css('width', percents + '%');
                            },
                            oncomplete: function(file, response) {
                                $('#upload-progress')
                                        .css('width', '100%')
                                        .addClass('progress-bar-success')
                                        .parent().removeClass('active');
                                $('#upload-submit').removeAttr('disabled');
                                $('#upload-cancel').attr('disabled', 'disabled');
                                message.show('success', 'Uploading complete.', '<a href="' + response.url + '">Download file</a>');
                            },
                            onstop: function(file) {
                                var url = $('.disk-usage-block').attr('data-refresh-url');
                                $.get(url, function(response) {
                                    $('#upload-submit').removeAttr('disabled');
                                    $('#upload-cancel').attr('disabled', 'disabled');
                                    $('#upload-progress').css('width', '0');
                                });
                                message.show('info', 'Upload interrupt');
                            },
                            onerror: function(file, text) {
                                if (typeof(text) !== 'undefined') {
                                    message.show('warning', 'Error', text);
                                } else {
                                    message.show('error', 'Error', 'Unable to upload ' + file.name);
                                }
                            }
                        });
                        $form.find('#upload-cancel').on('click', function() {
                            upload.stop();
                        });
                        upload.start();
                    } else {
                        message.show(
                                'warning',
                                'Form error',
                                'Please, select file'
                                );
                    }
                });
            }
        });
    </script>
</body>
</html>