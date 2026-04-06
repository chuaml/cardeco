# Context
- all PHP or HTML files are for displaying HTML to frontend UI
- files here are pull and executed from a corresponding file in @../request_handler/** 
- the @./_layout.main.html is being pull first to serve as main webpage HTML structure
    - this will include the @./template/head.php to serve in `<head>` section
    - and a file from @./** to serve in `<body>`
