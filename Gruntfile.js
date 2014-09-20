'use strict';
module.exports = function (grunt) {

    // load all grunt tasks
    require('matchdep').filterDev('grunt-*').forEach(grunt.loadNpmTasks);

    grunt.initConfig({

        // Define watch tasks
        watch: {
            options: {
                livereload: true
            },
            sass: {
                files: ['./assets/scss/**/*.scss'],
                tasks: ['sass', 'autoprefixer']
            },
            js: {
                files: ['./assets/js/source/*.js'],
                tasks: ['uglify']
            },
            livereload: {
                files: ['./**/*.html', './**/*.php', './assets/images/**/*.{png,jpg,jpeg,gif,webp,svg}']
            }
        },

        // SASS
        sass: {
            dist: {
                options: {
                    outputStyle: 'expanded',
                    imagePath: './assets/images'
                },
                files: {
                    './assets/css/frontend.main.min.css': './assets/scss/frontend.main.scss'
                }
            }
        },

        // Auto prefix our CSS with vendor prefixes
        autoprefixer: {
            dist: {
                expand: true,
                flatten: true,
                src: './assets/css/**/*.css',
                dest: './assets/css',
                options: {
                    browsers: ['last 2 version', 'ie 8', 'ie 9']
                }
            }
        },

        // Uglify and concatenate
        uglify: {
            dist: {
                files: {
                    './assets/js/rqc.main.min.js': [
                        './assets/js/source/*.js'
                    ]
                }
            }
        }

    });

    // Register our main task
    grunt.registerTask('Watch', ['watch']);

};