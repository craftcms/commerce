var gulp = require('gulp');
var sass = require('gulp-sass');
var concat = require('gulp-concat');

sass.compiler = require('node-sass');

var assetsPath = './src/web/assets/';

function commerceSass () {
    return gulp.src(assetsPath+'commercecp/src/scss/**/*.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(concat('commercecp.css'))
        .pipe(gulp.dest(assetsPath+'commercecp/dist/css'));
};

function commerceJs() {
    return gulp.src(assetsPath+'commercecp/src/**/*.js')
        .pipe(concat('commercecp.js'))
        .pipe(gulp.dest(assetsPath+'commercecp/dist/js'));
};

exports.default = function(done) {
    gulp.series(commerceJs, commerceSass)(done);
}

exports.watch = function() {
    gulp.watch(assetsPath+'commercecp/src/scss/**/*.scss', commerceSass);
    gulp.watch(assetsPath+'commercecp/src/**/*.js', commerceJs);
}