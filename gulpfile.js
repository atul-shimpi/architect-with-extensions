// Include gulp
var gulp = require('gulp'); 

// Include Our Plugins
var jshint = require('gulp-jshint');
var less   = require('gulp-less');
var minifyCSS = require('gulp-minify-css');
var path = require('path');
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');
var rename = require('gulp-rename');
var sourcemaps = require('gulp-sourcemaps');
var browserSync = require('browser-sync');
var autoprefixer = require('gulp-autoprefixer');
var reload      = browserSync.reload;

gulp.task('less', function () {
  gulp.src('./assets/less/builder.less')
  	//.pipe(sourcemaps.init())
    .pipe(less())
    .on('error', function (err) {
    	this.emit('end');
    })
   	.pipe(autoprefixer({
        browsers: ['last 2 versions'],
        cascade: false,
        remove: false
    }))
    //.pipe(sourcemaps.write())
    .pipe(minifyCSS())
    .pipe(gulp.dest('./assets/css'))
    .pipe(browserSync.reload({stream:true}));
});

gulp.task('iframeLess', function () {
  gulp.src('./assets/less/iframe.less')
    .pipe(less())
    .pipe(minifyCSS())
    .pipe(gulp.dest('./assets/css'))
    .pipe(browserSync.reload({stream:true}));
});

gulp.task('lint', function() {
    return gulp.src('js/*.js')
        .pipe(jshint())
        .pipe(jshint.reporter('default'));
});

// Concatenate & Minify JS
gulp.task('scripts', function() {
    return gulp.src([
    	'assets/js/vendor/beautify-html.js',
		'assets/js/vendor/jquery.js',
		'assets/js/vendor/icheck.js',
		'assets/js/vendor/moment.js',
		'assets/js/builder/utils/stringHelpers.js',
		'assets/js/vendor/jquery-ui.js',
		'assets/js/vendor/resizable.js',
		'assets/js/vendor/html2canvas.min.js',
		'assets/js/vendor/bootstrap/transition.js',
		'assets/js/vendor/bootstrap/collapse.js',
		'assets/js/vendor/bootstrap/modal.js',
		'assets/js/vendor/bootstrap/dropdown.js',
		'assets/js/vendor/bootstrap/alert.js',
		'assets/js/vendor/bootstrap/tooltip.js',
		'assets/js/vendor/pagination.js',
		'assets/js/vendor/jquery.mCustomScrollbar.js',
		'assets/js/vendor/jquery.mousewheel.js',
		'assets/js/vendor/toggles.js',
		'assets/js/vendor/alertify.js',
		'assets/js/vendor/rangy/rangy-core.js',
		'assets/js/vendor/rangy/rangy-cssclassapplier.js',
		'assets/js/vendor/spectrum.js',
		'assets/js/vendor/knob.js',
		'assets/js/vendor/zero-clip.min.js',
		'assets/js/vendor/angular.min.js',
		'assets/js/vendor/angular-animate.min.js',
		'assets/js/vendor/angular-cookies.js',
		'assets/js/vendor/angular-ui-router.min.js',
		'assets/js/vendor/angular-translate.js',
		'assets/js/vendor/angular-translate-url-loader.js',
		'assets/js/vendor/flow.js',
		'assets/js/builder/styling/fonts.js',
		'assets/js/builder/dragAndDrop/draggable.js',
		'assets/js/builder/dragAndDrop/iframeScroller.js',
		'assets/js/builder/dragAndDrop/resizable.js',
		'assets/js/builder/dragAndDrop/grid.js',
		'assets/js/builder/resources/icons.js',
		'assets/js/builder/resources/colors.js',
		'assets/js/builder/editors/wysiwyg.js',				
		'assets/js/builder/elements/definitions/bootstrap.js',
		'assets/js/builder/elements/definitions/base.js',
		'assets/js/builder/elements/panel.js',
		'assets/js/builder/elements/repository.js',
		'assets/js/builder/inspector/inspector.js',
		'assets/js/builder/inspector/attributes.js',
		'assets/js/builder/inspector/border.js',
		'assets/js/builder/inspector/marginPadding.js',
		'assets/js/builder/inspector/text.js',
		'assets/js/builder/inspector/shadows.js',
		'assets/js/builder/inspector/actions.js',
		'assets/js/builder/inspector/background/background.js',
		'assets/js/builder/inspector/background/mediaManagerController.js',
		'assets/js/builder/settings.js',
		'assets/js/builder/directives.js',
		'assets/js/builder/app.js',
		'assets/js/builder/controllers/navbarController.js',
		'assets/js/builder/controllers/linkerController.js',
		'assets/js/builder/controllers/dashboardController.js',
		'assets/js/builder/controllers/newProjectController.js',	
		'assets/js/builder/context/contextBoxes.js',
		'assets/js/builder/undoManager.js',
		'assets/js/builder/dom.js',			
		'assets/js/builder/context/contextMenu.js',
		'assets/js/builder/dragAndDrop/iframeDragAndDropWidget.js',
		'assets/js/builder/dragAndDrop/columnsResizeWidget.js',
		'assets/js/builder/editors/codeEditor.js',
		'assets/js/builder/editors/libraries.js',
		'assets/js/builder/styling/themes.js',
		'assets/js/builder/styling/templates.js',
		'assets/js/builder/styling/themesCreator.js',
		'assets/js/builder/styling/css.js',
		'assets/js/builder/utils/localStorage.js',
		'assets/js/builder/editors/imageEditor.js',
		'assets/js/builder/projects/project.js',
		'assets/js/builder/projects/pagesController.js',
		'assets/js/builder/projects/export.js',
		'assets/js/builder/projects/exportToFtp.js',
		'assets/js/builder/keybinds.js',
		'assets/js/builder/users/usersController.js',
		'assets/js/builder/dashboard/template.js',
		'assets/js/builder/translator.js',
		'assets/js/builder/**/**.js'
	])
	//.pipe(sourcemaps.init())
    .pipe(concat('builder.min.js'))
    //.pipe(sourcemaps.write())
    .pipe(uglify())
    .pipe(gulp.dest('assets/js')) 
    .pipe(browserSync.reload({stream:true}));
});

// Watch Files For Changes
gulp.task('watch', function() {
	browserSync({
        proxy: "localhost/architect/"
    });

    gulp.watch('assets/js/**/*.js', ['scripts']);
    gulp.watch('assets/less/iframe.less', ['iframeLess']);
    gulp.watch('assets/less/**/*.less', ['less']);
    gulp.watch('views/*.html').on('change', reload);
});

// Default Task
gulp.task('default', ['less', 'scripts', 'watch']);