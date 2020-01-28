var gulp = require('gulp');
var es = require('event-stream');
var sass = require('gulp-sass');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');
var uglify = require('gulp-uglify');
sass.compiler = require('node-sass');

var assetsPath = './src/web/assets/';
var libPath = './lib/';

var jsDeps = [
    { srcGlob: 'node_modules/chart.js/dist/Chart.bundle.min.js', dest: libPath + 'chart-js' },
    { srcGlob: 'node_modules/moment/min/moment-with-locales.min.js', dest: libPath + 'moment' },
    { srcGlob: 'node_modules/chartjs-adapter-moment/dist/chartjs-adapter-moment.min.js', dest: libPath + 'chartjs-adapter-moment' },
    { srcGlob: 'node_modules/deepmerge/dist/umd.js', dest: libPath + 'deepmerge' },
];

function commerceStatsSass () {
    return gulp.src(assetsPath+'statwidgets/src/scss/**/*.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(concat('statwidgets.css'))
        .pipe(gulp.dest(assetsPath+'statwidgets/dist/css'));
};

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
                // .pipe(uglify())
                //.pipe(rename({ suffix: '.min' }))
                .pipe(sourcemaps.write('./'))
                .pipe(gulp.dest(dep.dest))
        );
    });

    return es.merge(streams);
};


exports.default = function(done) {
    libDeps();
    gulp.series(commerceJs, commerceSass, commerceStatsSass)(done);
}

exports.watch = function() {
    gulp.watch(assetsPath+'commercecp/src/scss/**/*.scss', commerceSass);
    gulp.watch(assetsPath+'statwidgets/src/scss/**/*.scss', commerceStatsSass);
    gulp.watch(assetsPath+'commercecp/src/**/*.js', commerceJs);
}