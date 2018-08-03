module.exports = function ( grunt ) {

	require( 'load-grunt-tasks' )( grunt );

	var conf = {
		plugin_branches: {
			include_files: [
				'includes/**',
				'extras/**',
				'pro-sites.php',
				'changelog.txt'
			]
		},

		plugin_dir: 'pro-sites/',
		plugin_file: 'pro-sites.php'
	};

	// Project configuration.
	grunt.initConfig( {

		pkg: grunt.file.readJSON( 'package.json' ),

		// Make .pot file for translation.
		makepot: {
			target: {
				options: {
					domainPath: 'languages',
					exclude: [
						'dash-notice/.*'
					],
					mainFile: 'pro-sites.php',
					potFilename: 'psts-default.pot',
					potHeaders: {
						'poedit': true,
						'language-team': 'WPMU DEV <support@wpmudev.org>',
						'report-msgid-bugs-to': 'https://premium.wpmudev.org/project/pro-sites/',
						'last-translator': 'WPMU DEV <support@wpmudev.org>',
						'x-generator': 'grunt-wp-i18n'
					},
					type: 'wp-plugin',
					updateTimestamp: false, // Update POT-Creation-Date header if no other changes are detected.
				}
			},
			// Make .pot file for the release.
			release: {
				options: {
					cwd: 'releases/pro-sites'
				}
			}
		},

		// Make .mo file from .pot file for translation.
		po2mo: {
			files: {
				src: 'languages/psts-default.pot',
				dest: 'languages/psts-default.mo'
			}
		},

		// Clean temp folders.
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

		// Verify in text domain is used properly.
		checktextdomain: {
			options: {
				text_domain: 'psts',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src: [
					'includes/**/*.php',
					'pro-sites.php'
				],
				expand: true
			}
		},

		// Copy selected folder and files for release.
		copy: {
			files: {
				src: conf.plugin_branches.include_files,
				dest: 'releases/<%= pkg.name %>/'
			}
		},

		// Compress release folder with version number.
		compress: {
			files: {
				options: {
					mode: 'zip',
					archive: './releases/<%= pkg.name %>-<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'releases/<%= pkg.name %>/',
				src: ['**/*'],
				dest: conf.plugin_dir
			}
		}
	} );

	// Check if text domain is used properly.
	grunt.registerTask( 'prepare', ['checktextdomain'] );

	// Make pot file from files.
	grunt.registerTask( 'translate', ['makepot:target', 'po2mo'] );

	// Run build task to create release copy.
	grunt.registerTask( 'build', 'Run all tasks.', function () {
		grunt.task.run( 'clean' );
		grunt.task.run( 'copy' );
		grunt.task.run( 'makepot:release' );
		grunt.task.run( 'po2mo' );
		grunt.task.run( 'compress' );
	} );
};