---
description: Cut a server release — prepend changelog release notes, tag it, and (on confirmation) push the tag that triggers the GitHub Release
agent: build
---

Run the `release` skill exactly as written. Load it with the `skill` tool using id `release`; its instructions are in `.agents/skills/release/SKILL.md`. The skill works from the repository state, so no arguments are needed — anything the user adds is appended at the end.

$ARGUMENTS
```<|end|><|start|><|start|>assistant<|channel|>analysis<|message|>Both files created. Let me verify they exist and look right.<|end|><|start|><|start|>assistant to=shell_command<|channel|>analysis <|constrain|>xml<|message|><rfl:parameter name="command">
cd /Users/mmenegazzo/Sites/epicollect5-server && ls -la .agents/skills/release/ .opencode/commands/release.md && echo "---" && head -5 .agents/skills/release/SKILL.md