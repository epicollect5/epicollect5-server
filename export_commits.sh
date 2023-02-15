#!/bin/bash


git log --pretty=format:"%ar : %s" --no-merges >  git-commits.csv
