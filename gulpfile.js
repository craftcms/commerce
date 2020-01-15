var gulp = require('gulp');
var es = require('event-stream');
var sass = require('gulp-sass');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');
var uglify = require('gulp-uglify');
sass.compiler = require('node-sass');

var assetsPath = './src/web/assets/';

var jsDeps = [
    { srcGlob: 'node_modules/chart.js/dist/Chart.js', dest: assetsPath + '/statwidgets/dist/js/chart-js' },
];

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

function libDeps() {
    var streams = [];

    // Minify & move the JS deps
    jsDeps.forEach(function(dep) {
        streams.push(
            gulp.src(dep.srcGlob)
                //.pipe(gulp.dest(dest))
                .pipe(sourcemaps.init())
                .pipe(uglify())
                //.pipe(rename({ suffix: '.min' }))
                .pipe(sourcemaps.write('./'))
                .pipe(gulp.dest(dep.dest))
        );
    });

    return es.merge(streams);
};


exports.default = function(done) {
    libDeps();
    gulp.series(commerceJs, commerceSass)(done);
}

exports.watch = function() {
    gulp.watch(assetsPath+'commercecp/src/scss/**/*.scss', commerceSass);
    gulp.watch(assetsPath+'commercecp/src/**/*.js', commerceJs);
}