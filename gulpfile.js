var gulp = require('gulp');
var elixir = require('laravel-elixir');
var argv = require('yargs').argv;

elixir.config.assetsPath = 'source/_assets';
elixir.config.publicPath = 'source';

elixir(function(mix) {
    var env = argv.e || argv.env || 'local';

    mix.sass('main.scss')
        .copy('./node_modules/bootstrap/dist/css/bootstrap.min.css','build_local/css/bootstrap.min.css')
        .copy('./node_modules/bootstrap/dist/js/bootstrap.min.js','build_local/js/bootstrap.min.js')
        .copy('./node_modules/jquery/dist/jquery.min.js','build_local/js/jquery.min.js')
        .exec('jigsaw build ' + env, ['./source/*', './source/**/*', '!./source/_assets/**/*'])
        .browserSync({
            server: { baseDir: 'build_' + env },
            proxy: null,
            files: [ 'build_' + env + '/**/*' ]
        });
});
