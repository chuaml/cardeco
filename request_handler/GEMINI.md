# Context
- all PHP files here map to a pathname directly
- PHP files here is executed when its corresponding pathname is navigated to
- once a PHP file here is executed, its last step is to pull files from @../view/** to display HTML to frontend webpage UI
- sadly, not all legacy pages are mapped and follow this pattern yet; some pathname navigation bypass this directory @./** directly
