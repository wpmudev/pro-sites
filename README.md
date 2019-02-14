# Pro Sites

Before starting development make sure you read and understand everything in this README.

## Working with Git

Clone the plugin repo and checkout the `development` branch

```
# git clone git@bitbucket.org:incsub/pro-sites.git --recursive
# git fetch && git checkout development
```

Install/update the necessary submodules if the branch is already checked out

```
# git submodule init --
# git submodule update  
```

## Installing dependencies and initial configuration

Install Node
```
# curl -sL https://deb.nodesource.com/setup_10.x | sudo -E bash -
# sudo apt-get install -y nodejs build-essential
```

Install the necessary npm modules and packages
```
# npm install
``` 

Set up username and email for Git commits
```
# git config user.email "<your email>"
# git config user.name "<your name>"
```

## Grunt tasks

Everything (except unit tests) should be handled by npm. Note that you don't need to interact with Grunt in a direct way.

Command | Action
------- | ------
`grunt translate` | Build pot and mo file inside /pro-sites-files/languages/ folder
`grunt build` | Build release version, useful to provide packages to QA without doing all the release tasks

## Versioning

Follow semantic versioning [http://semver.org/](http://semver.org/) as `package.json` won't work otherwise. That's it:

- `X.X.0` for major versions
- `X.X.X` for minor versions
- `X.X[.X||.0]-beta.1` for betas (QA builds)

## Workflow

Do not commit on `master` branch (should always be synced with the latest released version). `development` is the code
that accumulates all the code for the next version.

- Create a new branch from `development` branch: `git checkout -b branch-name origin/development`. Try to give it a descriptive name. For example:
    * `release/X.X.X` for next releases
    * `new/some-feature` for new features
    * `enhance/some-enhancement` for enhancements
    * `fix/some-bug` for bug fixing
- Make your commits and push the new branch: `git push -u origin branch-name`
- File the new Pull Request against `development` branch
- Assign somebody to review your code.
- Once the PR is approved and finished, merge it in `development` branch.
- Delete your branch locally and make sure that it does not longer exist remote.

It's a good idea to create the Pull Request as soon as possible so everybody knows what's going on with the project
from the PRs screen in Bitbucket.

## How to release?

Prior to release, code needs to be checked and tested by QA team. Merge all active Pull Requests into `development` branch. Build the release with `grunt build` script and send the zip files to QA.

Follow these steps to make the release:

* Update `changelog.text` file.
* Do not forget to update the version number. Always with format X.X.X. You'll need to update in `pro-sites.php` (header and $version variable) and also `package.json`
* Execute `grunt build`. zips and files will be generated in `releases` folder.
* Once QA passed, do not forget to sync `master` on `development` by checking out `development` branch and then `git merge master`