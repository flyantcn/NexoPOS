# === 首次配置（只做一次） ===
git remote add upstream https://github.com/Blair2004/NexoPOS.git

# 本地改动备份到 fork
git push origin master   

# === 每次同步上游 ===
git fetch upstream
git log HEAD..upstream/master --oneline        # 看有哪些更新
git checkout master
git merge upstream/master                      # 有冲突则解决后 git add + git commit
git push origin master

# 换机器时拉取 fork 最新
git pull origin master   

# === 查看所有 remote ===
git remote -v

# 想查看自己相对上游到底改了什么：
git diff upstream/master..HEAD --stat


# 改代码...

# 提交推送
git add -A
git commit -m "描述改动"
git push origin master
