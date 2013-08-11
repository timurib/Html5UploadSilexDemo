(function(thisModule) {
    // Try to load as ARM module (for RequireJS)…
    console.log('Init module html5upload');
    if (typeof define === 'function' && define.amd) {
        define([], thisModule);
    } else {
        window.html5upload = thisModule; // …or fallback set global var
    }
}(function() {
    /**
     * Constructor
     *
     * @param {Object} params
     * @param {Object} handlers
     */
    function Upload(params, handlers) {
        this.id = null;
        this.params = params;
        this.callbacks = {
            onstart: handlers.onstart || function() {
            },
            onchunk: handlers.onchunk || function() {
            },
            oncomplete: handlers.oncomplete || function() {
            },
            onstop: handlers.onstop || function() {
            },
            onerror: handlers.onerror || function() {
            }
        };
        this.stopped = false;
    }
    ;

    /**
     * Start upload
     */
    Upload.prototype.start = function() {
        this.started = true;
        console.log('Start uploading', this.params);
        var headers = this.describe(this.params.file);
        headers['X-Upload-State'] = 'start';
        this.post(headers, function(response) {
            if (response && response.id !== undefined && response.status === 'upload_start') {
                this.callbacks.onstart(this.params.file);
                this.id = response.id;
                this.step(response.id);
            } else {
                console.log('Error: upload not started');
                this.callbacks.onerror(this.params.file, response.message);
            }
        });
    };

    /**
     * Uploading process step (recursive)
     */
    Upload.prototype.step = function() {
        var headers = this.describe(this.params.file);
        headers['X-Upload-Id'] = this.id;
        this.params.chunkIndex = this.params.chunkIndex || 0;
        this.params.byteCounter = this.params.byteCounter || 0;
        var offset = this.params.chunkIndex * this.params.chunkSize;
        if (offset < this.params.file.size - 1) {
            var chunk = this.params.file.slice(offset, offset + this.params.chunkSize, 'application/octet-binary');
            headers['X-Chunk-Size'] = chunk.size;
            headers['X-Upload-State'] = 'chunk';
            this.post(headers, function(response) {
                this.params.byteCounter += chunk.size;
                if (response && response.status === 'chunk_received') {
                    if (response.received === chunk.size) {
                        ++this.params.chunkIndex;
                        var percents = 100 * (this.params.byteCounter / this.params.file.size);
                        this.callbacks.onchunk(this.params.file, this.params.chunkIndex, this.params.byteCounter, percents);
                        this.step();
                    } else {
                        console.log('Errors when uploading chunk (#' + this.params.chunkIndex + ')');
                        this.callbacks.onerror(this.params.file, response.message);
                    }
                } else {
                    console.log('Error: chunk is not uploaded');
                    this.callbacks.onerror(this.params.file, response.message);
                }
            }, chunk);
        } else {
            headers['X-Upload-State'] = 'complete';
            this.post(headers, function(response) {
                if (response.status === 'upload_complete') {
                    console.log('Uploading complete');
                    this.callbacks.oncomplete(this.params.file, response);
                } else {
                    console.log('Error: upload is not completed');
                    this.callbacks.onerror(this.params.file);
                }
            });
        }
    };

    /**
     * Interrupt uploading
     */
    Upload.prototype.stop = function() {
        if (this.xhr && !this.stopped) {
            this.stopped = true;
            console.log('Stop upload');
            this.xhr.abort();
            this.callbacks.onstop(this.params.file);
        }
    };

    /**
     * AJAX POST request
     *
     * @param {Object} headers
     * @param {Function} onresponse
     * @param {Object} data
     */
    Upload.prototype.post = function(headers, onresponse, data) {
        if (this.stopped) {
            return;
        }
        this.xhr = new XMLHttpRequest();
        this.xhr.open('POST', this.params.url, true);
        this.xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        this.xhr.setRequestHeader('X-Request-Unique-Id', Math.floor(Math.random() * 100000));
        for (var name in headers) {
            this.xhr.setRequestHeader(name, headers[name]);
        }
        var upload = this;
        this.xhr.onreadystatechange = function() {
            if (upload.xhr.readyState === 4) {
                if (upload.xhr.status === 200) {
                    try {
                        var response = upload.decode(upload.xhr);
                        onresponse.call(upload, response);
                    } catch (e) {
                        console.log('JSON error');
                        upload.callbacks.onerror(upload.params.file);
                    }
                } else if (!upload.stopped) {
                    console.log('Error: response status ' + upload.xhr.status);
                    upload.callbacks.onerror(upload.params.file);
                }
            }
        };
        this.xhr.send(data || ''); // fix potentially promlems in some browsers
    };

    /**
     * Build additionally HTTP-headers for file
     *
     * @param {File} file
     * @returns {Object}
     */
    Upload.prototype.describe = function(file) {
        return {
            'X-File-Size': file.size,
            'X-File-Name': file.name,
            'X-File-Type': file.type
        };
    };

    /**
     * JSON parse helper
     *
     * @param {XMLHttpRequest} xhr
     */
    Upload.prototype.decode = function(xhr) {
        return JSON.parse(xhr.responseText);
    };

    /**
     * Check browser support required API
     *
     * @returns {Boolean}
     */
    Upload.support = function() {
        return typeof(XMLHttpRequest) !== 'undefined'
                && typeof(File) !== 'undefined'
                && typeof(File.prototype.slice) !== 'undefined'
                && typeof(JSON) !== 'undefined'
                && typeof(JSON.parse) !== 'undefined';
    };

    return {
        Upload: Upload
    };
}()));