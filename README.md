# Checklist REMOVE AFTER CHECKING 

Go through this checklist after creating your repository. It should only take a couple of minutes. If you encounter any issues, let someone from IT know.

### README
- [ ] Manually go through and edit the rest of the README.

### Dotfiles
- [ ] Add a `.gitignore` file.

### GitHub Settings
- [ ] Add a short description to the repository.
- [ ] Add a develop branch.
- [ ] Set develop branch as default branch.
- [ ] Enable all data services (read-only analysis, dependency graph, security alerts).
- [ ] Create branch protection rules for master:
  - [ ] Require pull request review before merging.
    - [ ] Require 2 reviews. (One for the code and testing (DevOps), and one for semantics)
    - [ ] Dismiss stale pull request approvals when new commits are pushed.
  - [ ] Require status checks before merging.
- [ ] Create branch protection rules for develop:
  - [ ] Require pull request review before merging.
    - [ ] Require 2 reviews. (One for the code and testing (DevOps), and one for semantics)
    - [ ] Dismiss stale pull request approvals when new commits are pushed.
  - [ ] Require status checks before merging.
- [ ] Add a .travis.yml file.
  - [ ] Add the codecov token to env variables.
- [ ] [OPTIONAL] Add a codecov.yml
- [ ] Enable the status checks for travis and codecov.

# Project Title

One Paragraph of project description goes here

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

What things you need to install the software and how to install them

```
Give examples
```

### Installing

A step by step series of examples that tell you how to get a development env running

Say what the step will be

```
Give the example
```

And repeat

```
until finished
```

End with an example of getting some data out of the system or using it for a little demo

## Running the tests

Explain how to run the automated tests for this system.

### Break down into end to end tests

Explain what these tests test and why.

```
Give an example
```

### And coding style tests

Explain what these tests test and why.

```
Give an example
```

## Deployment

Add additional notes about how to deploy this on a live system

## Authors

* **YOUR NAME HERE** - *Initial work*

See also the list of [contributors](https://github.com/marketredesign/your_project/contributors) who participated in this project.

## License

This project is licensed under the MRD License - see the [LICENSE](LICENSE) file for details
