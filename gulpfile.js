// gulpfile.js - menggunakan CommonJS
const gulp = require('gulp');
const rename = require('gulp-rename');
const gulpif = require('gulp-if');
const terser = require('gulp-terser');
const path = require('path');

// Define folders to process
const targetFolders = [
  'resource/assets-frontend/js/Master-Products/**/*.js',
  'resource/assets-frontend/js/Cashier/**/*.js',
  'resource/assets-frontend/js/Member/**/*.js',
  'resource/assets-frontend/js/Order/**/*.js',
  'resource/assets-frontend/js/Product/**/*.js',
  'resource/assets-frontend/js/Promo/**/*.js'
];

// Fungsi untuk membuat backup file original dengan suffix .dev
function backupOriginalJs() {
  return gulp.src(targetFolders)
    .pipe(gulpif(file => !file.path.includes('.min.js') && !file.path.includes('.dev.js'), 
      rename(function(path) {
        // Simpan file asli dengan suffix .dev
        path.basename += '.dev';
      })
    ))
    .pipe(gulp.dest(function(file) {
      return file.base;
    }));
}

// Fungsi untuk memproses JS
function processJs() {
  const processPatterns = targetFolders.map(folder => folder.replace('.js', '.dev.js'));
  
  return gulp.src(processPatterns)
    .pipe(terser({
      compress: {
        drop_console: true
      }
    }))
    .pipe(rename(function(path) {
      // Hapus suffix .dev untuk file hasil proses
      path.basename = path.basename.replace('.dev', '');
    }))
    .pipe(gulp.dest(function(file) {
      return file.base;
    }));
}

// Task untuk membuat backup dan memproses
const jsTask = gulp.series(backupOriginalJs, processJs);

// Export tasks
exports.default = jsTask;
exports.processJs = jsTask;