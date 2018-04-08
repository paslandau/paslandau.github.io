#Dev
- Add article in the `source/blog` directory
- pics go in `/source/img`
- Watch for changes
  - `gulp watch`
  - Opens `http://localhost:3000/`
- `jigsaw build local`
  - builds the blog locally
- `gulp`
  - compiles assests and
  - builds the blog locally

Deploy
- `blog-build`
  - commits the current status of this repo
  - runs `gulp` (see above; but from anywhere; changes to this dir before)
- `blog-publish`
  - copies files from `build_local` to the blog directory and pushes them to github