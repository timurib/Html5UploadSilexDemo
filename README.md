Demo of upload files in Silex via HTML5 API and PHP streams.

Files split to chunks and step by step sends to server, where binary data readed from php://input and collect in temporary file, until upload process complete.
Metadata transmits in extra HTTP-headers, server response come in JSON format. Base client functions can be found in html5upload.js (may be used as AMD-module and not require jQuery).

*This is just sample — do not use this in production, code is not tested!*

Works in only modern browsers (>=IE10).