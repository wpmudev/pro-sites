const _ = require( 'lodash' ),
	path = require( 'path' ),
	webpack = require( 'webpack' ),
	ATP = require( 'autoprefixer' ),
	CSSExtract = require( "mini-css-extract-plugin" );

// The path where the Shared UI fonts & images should be sent.
const config = {
	output: {
		imagesDirectory: '../images',
		fontsDirectory: '../fonts'
	}
};

const sharedConfig = {
	mode: 'production',

	stats: {
		colors: true,
		entrypoints: true
	},

	watchOptions: {
		ignored: /node_modules/,
		poll: 1000
	}
};

const scssConfig = _.assign( _.cloneDeep( sharedConfig ), {
	entry: {
		'checkout': './_src/scss/checkout.scss',
		'plans-pricing': './_src/scss/plans-pricing.scss',
		'admin': './_src/scss/admin.scss',
		'quota': './_src/scss/quota.scss',
		'option1': './_src/scss/pricing-tables/option1.scss',
		'option2': './_src/scss/pricing-tables/option2.scss',
		'option3': './_src/scss/pricing-tables/option3.scss',
		'option4': './_src/scss/pricing-tables/option4.scss',
		'option5': './_src/scss/pricing-tables/option5.scss'
	},

	output: {
		filename: '[name].min.css',
		path: path.resolve( __dirname, 'assets/css' )
	},

	module: {
		rules: [
			{
				test: /\.scss$/,
				exclude: /node_modules/,
				use: [CSSExtract.loader,
					{
						loader: 'css-loader'
					},
					{
						loader: 'postcss-loader',
						options: {
							plugins: [
								ATP( {
									browsers: ['ie > 9', '> 1%']
								} )
							],
							sourceMap: true
						}
					},
					{
						loader: 'resolve-url-loader'
					},
					{
						loader: 'sass-loader',
						options: {
							sourceMap: true
						}
					}
				]
			},
			{
				test: /\.(png|jpg|gif)$/,
				use: {
					loader: 'file-loader', // Instructs webpack to emit the required object as file and to return its public URL.
					options: {
						name: '[name].[ext]',
						outputPath: config.output.imagesDirectory
					}
				}
			},
			{
				test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
				use: {
					loader: 'file-loader', // Instructs webpack to emit the required object as file and to return its public URL.
					options: {
						name: '[name].[ext]',
						outputPath: config.output.fontsDirectory
					}
				}
			}
		]
	},

	plugins: [
		new CSSExtract( {
			filename: '../css/[name].min.css'
		} )
	]
} );

const jsConfig = _.assign( _.cloneDeep( sharedConfig ), {
	entry: {
		'external/jquery.flot': [
			'jquery.flot/jquery.flot.js',
			'jquery.flot/jquery.flot.time.js',
			'jquery.flot/jquery.flot.pie.js',
		],
		'external/excanvas': 'jquery.flot/excanvas.js',
		'admin': './_src/js/modules/admin.js',
		'checkout': './_src/js/modules/checkout.js',
		'levels': './_src/js/modules/levels.js',
		'quota': './_src/js/modules/quota.js',
		'stripe': './_src/js/modules/stripe.js',
		'tax': './_src/js/modules/tax.js'
	},

	output: {
		filename: '[name].min.js',
		path: path.resolve( __dirname, 'assets/js' )
	},

	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['env', 'react']
					}
				}
			}
		]
	},

	//devtool: 'source-map',
} );

module.exports = [scssConfig, jsConfig];
