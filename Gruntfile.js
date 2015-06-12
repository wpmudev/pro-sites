module.exports = function(grunt) {
  
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
		            type: 'wp-plugin'
		        }
		    }
		},
		wpmu_pot2mo: {
		    files: {
		        src: 'pro-sites-files/languages/*.pot',
		        expand: true,
		    },
		},
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
	

};