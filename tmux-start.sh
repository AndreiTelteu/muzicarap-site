#!/usr/bin/env bash

set -euo pipefail

repo_dir='/home/andrei/muzicarap'

tmux has-session -t 'muzicarap-queue' 2>/dev/null || tmux new-session -d -s 'muzicarap-queue' "cd ${repo_dir} && php artisan queue:work --tries=1 --timeout=0"
tmux has-session -t 'muzicarap-start' 2>/dev/null || tmux new-session -d -s 'muzicarap-start' "cd ${repo_dir} && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8444 --watch"
