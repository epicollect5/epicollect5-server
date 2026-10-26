---
description: Cut a server release — prepend changelog release notes, tag it, and (on confirmation) push the tag that triggers the GitHub Release
agent: build
---

Execute the `release` skill defined in `.agents/skills/release/SKILL.md` exactly as written.

Forward the arguments below verbatim to the skill as-is, without parsing, restructuring, or interpreting them:

$ARGUMENTS
```<|end|><|start|><|start|>assistant<|channel|>analysis<|message|>Both files created. Let me verify they exist and look right.<|end|><|start|><|start|>assistant to=shell_command<|channel|>analysis <|constrain|>xml<|message|><rfl:parameter name="command">
cd /Users/mmenegazzo/Sites/epicollect5-server && ls -la .agents/skills/release/ .opencode/commands/release.md && echo "---" && head -5 .agents/skills/release/SKILL.md