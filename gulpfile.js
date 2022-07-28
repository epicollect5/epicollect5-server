'use strict';
var gulp = require('gulp');
var sass = require('gulp-sass');
var phpunit = require('gulp-phpunit');
var concatCss = require('gulp-concat-css');
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');
var cleanCSS = require('gulp-clean-css');

gulp.task('phpunit', function () {
    gulp.src('phpunit.xml').pipe(phpunit());
});

gulp.task('watch-sass', function () {
    //watch sass file and compile
    gulp.watch('./resources/assets/sass/**/*.scss', gulp.series('sass'));
});

gulp.task('watch-js', function () {
    //watch js file and compile
    gulp.watch('./resources/assets/js/**/*.js',
        gulp.series('project-js', 'projects-js', 'site-js', 'admin-js', 'users-js')
    );
    gulp.watch('./resources/assets/js/**/**/*.js', gulp.series('project-js', 'projects-js', 'site-js', 'admin-js', 'users-js'));
});

//compile sass to css
gulp.task('sass', async function compileSass() {
    gulp.src('./resources/assets/sass/site.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(cleanCSS({ processImport: false }))
        .pipe(gulp.dest('./public/css/'));
});

//concat vendor site css files
gulp.task('vendor-site-css', function () {
    return gulp.src('./resources/assets/css/vendor-site/*.css')
        .pipe(concatCss('vendor-site.css'))
        .pipe(cleanCSS())
        .pipe(gulp.dest('./public/css/'));
});
//concat vendor formbuilder css files
gulp.task('vendor-formbuilder-css', function () {
    return gulp.src('./resources/assets/css/vendor-formbuilder/*.css')
        .pipe(concatCss('vendor-formbuilder.css'))
        .pipe(cleanCSS())
        .pipe(gulp.dest('./public/css/'));
});

//concat vendor dataviewer css files
gulp.task('vendor-dataviewer-css', function () {
    return gulp.src('./resources/assets/css/vendor-dataviewer/*.css')
        .pipe(concatCss('vendor-dataviewer.css'))
        .pipe(cleanCSS())
        .pipe(gulp.dest('./public/css/'));
});

/* Build js dependencies for Epicollect5 components */
//site
gulp.task('site-js', function () {
    return gulp.src(['./resources/assets/js/site/*.js'])
        .pipe(concat('site.js'))
        // .pipe(uglify())
        .pipe(gulp.dest('./public/js/'));
});

gulp.task('projects-js', function () {
    return gulp.src(['./resources/assets/js/projects/**/*.js'])
        .pipe(concat('projects.js'))
        //.pipe(uglify())
        .pipe(gulp.dest('./public/js/projects/'));
});

gulp.task('users-js', function () {
    return gulp.src(['./resources/assets/js/users/**/*.js'])
        .pipe(concat('users.js'))
        //.pipe(uglify())
        .pipe(gulp.dest('./public/js/users/'));
});

gulp.task('project-js', function () {
    return gulp.src(['./resources/assets/js/project/**/*.js'])
        .pipe(concat('project.js'))
        .pipe(gulp.dest('./public/js/project/'));
});

gulp.task('admin-js', function () {
    return gulp.src(['./resources/assets/js/admin/*.js'])
        .pipe(concat('admin.js'))
        .pipe(gulp.dest('./public/js/admin/'));
});

gulp.task('site-js-prod', function () {
    return gulp.src(['./resources/assets/js/site/*.js'])
        .pipe(concat('site.js'))
        .pipe(uglify())
        .pipe(gulp.dest('./public/js/'));
});

gulp.task('projects-js-prod', function () {
    return gulp.src(['./resources/assets/js/projects/**/*.js'])
        .pipe(concat('projects.js'))
        .pipe(uglify())
        .pipe(gulp.dest('./public/js/projects/'));
});

gulp.task('users-js-prod', function () {
    return gulp.src(['./resources/assets/js/users/**/*.js'])
        .pipe(concat('users.js'))
        .pipe(uglify())
        .pipe(gulp.dest('./public/js/users/'));
});

gulp.task('project-js-prod', function () {
    return gulp.src(['./resources/assets/js/project/**/*.js'])
        .pipe(concat('project.js'))
        .pipe(uglify())
        .pipe(gulp.dest('./public/js/project/'));
});

gulp.task('admin-js-prod', function () {
    return gulp.src(['./resources/assets/js/admin/*.js'])
        .pipe(concat('admin.js'))
        .pipe(uglify())
        .pipe(gulp.dest('./public/js/admin/'));
});


//site vendor
gulp.task('vendor-site-js', function () {
    return gulp.src(['./resources/assets/js/vendor-site/jquery-2.1.4.min.js', './resources/assets/js/vendor-site/*.js'])
        .pipe(concat('vendor-site.js'))
        .pipe(uglify())
        .pipe(gulp.dest('./public/js/'));
});

//dataviewer
gulp.task('vendor-dataviewer-js', function () {
    return gulp.src(['./resources/assets/js/vendor-dataviewer/*.js'])
        .pipe(concat('vendor-dataviewer.js'))
        .pipe(uglify())
        .pipe(gulp.dest('./public/js/'));
});

gulp.task('default', gulp.series(
    'sass',
    'vendor-site-css',
    'vendor-formbuilder-css',
    'vendor-dataviewer-css',
    'site-js-prod',
    'vendor-site-js',
    'vendor-dataviewer-js',
    'projects-js-prod',
    'project-js-prod',
    'admin-js-prod',
    'users-js-prod'
));

gulp.task('watch', function(){
    //watch sass file and compile
    gulp.watch('./resources/assets/sass/**/*.scss', gulp.series('sass'));

    //watch js and compile (no compression)
    gulp.watch('./resources/assets/js/**/*.js', gulp.series('project-js', 'projects-js', 'site-js', 'admin-js', 'users-js'));
});

