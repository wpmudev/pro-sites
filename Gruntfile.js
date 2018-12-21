module.exports = function(grunt) {
	
	require( 'load-grunt-tasks' )(grunt);
	
	var conf = {
		plugin_branches: {
			include_files: [
				'**',
				'!**/node_modules/**',
				'!**/tests/**',
				'!**/releases/*.zip',
				'!releases/*.zip',
				'!**/releases/**',
				'!**/Gruntfile.js',
				'!**/package.json',
				'!**/build/**',
                '!**/bin/**',
                '!**/src/**',
				'!node_modules/**',
				'!.sass-cache/**',
				'!releases/**',
				'!Gruntfile.js',
				'!package.json',
				'!build/**',
				'!tests/**',
				'!.git/**',
				'!.git',
				'!**/.svn/**',
				'!.log'
			]
		},
        
        plugin_dir: 'pro-sites/',
		plugin_file: 'pro-sites.php'
	};
  
	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		makepot: {
		    target: {
		        options: {
					domainPath: '/pro-sites-files/languages',
					mainFile: 'pro-sites.php',
					potFilename: 'psts-default.pot',
					potHeaders: {
						'poedit': true,
						'language-team': 'WPMU Dev <support@wpmudev.org>',
						'report-msgid-bugs-to': 'https://premium.wpmudev.org/project/pro-sites/',
						'last-translator': 'WPMU Dev <support@wpmudev.org>',
						'x-generator': 'grunt-wp-i18n'
					},
		            type: 'wp-plugin',
			        updateTimestamp: false
		        }
		    }
		},
		wpmu_pot2mo: {
		    files: {
		        src: 'pro-sites-files/languages/*.pot',
		        expand: true
		    }
		},
		
		clean: {
			temp: {
				src: [
					'**/*.tmp',
					'**/.afpDeleted*',
					'**/.DS_Store'
				],
				dot: true,
				filter: 'isFile'
			}
        },
        
        copy: {
            files: {
                src: conf.plugin_branches.include_files,
                dest: 'releases/<%= pkg.name %>-<%= pkg.version %>/'
            }
		},
        
        compress: {
            files: {
                options: {
                    mode: 'zip',
                    archive: './releases/<%= pkg.name %>-<%= pkg.version %>.zip'
                },
                expand: true,
                cwd: 'releases/<%= pkg.name %>-<%= pkg.version %>/',
                src: [ '**/*' ],
                dest: conf.plugin_dir
            }
		}
	});

	// Load wp-i18n
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	

	// Adapted from https://github.com/MicheleBertoli/grunt-po2mo
	grunt.registerMultiTask('wpmu_pot2mo', 'Compile .pot files into binary .mo files with msgfmt.', function() {
		this.files.forEach(function(file) {

		  var dest = file.dest;
		  if (dest.indexOf('.pot') > -1) {
		      dest = dest.replace('.pot', '.mo');
		  }
		  grunt.file.write(dest);

		  var exec = require('child_process').exec;
		  var command = 'msgfmt -o ' + dest + ' ' + file.src[0];

		  grunt.verbose.writeln('Executing: ' + command);
		  exec(command);

		});
	});
	
	
	// Default task(s).
	grunt.registerTask( 'default', ['makepot', 'wpmu_pot2mo'] );
	
	grunt.registerTask( 'build', 'Run all tasks.', function() {

		// Run the default tasks (js/css/php validation).
		grunt.task.run( 'default' );

        grunt.task.run( 'clean' );        
        grunt.task.run( 'copy' );
		grunt.task.run( 'compress' );
	});
	

};