<?php /* UDeck help content (English). Loaded by help.php. */ ?>
<div class="docwrap">
  <nav class="toc" aria-label="Contents">
    <p class="toc-h">Contents</p>
    <ol>
      <li><a href="#overview">Overview</a></li>
      <li><a href="#start">Getting started</a></li>
      <li><a href="#board">Using the board</a></li>
      <li><a href="#records">Work records</a></li>
      <li><a href="#google">Google sync (Apps Script)</a></li>
      <li><a href="#agent">Automatic PC logging (install.cmd)</a></li>
      <li><a href="#handoff">HANDOFF.md</a></li>
      <li><a href="#settings">Settings</a></li>
      <li><a href="#trouble">Troubleshooting</a></li>
      <li><a href="#backup">Backup and security</a></li>
    </ol>
  </nav>

  <article class="doc">
    <h1>UDeck Help</h1>
    <p class="doc-lead">UDeck is a work desk board for checking and continuing work done on several PCs with several AI tools, all on one screen. It shows your Google schedule, tasks and mail together with per-PC work records, projects and notes.</p>

    <!-- 1 -->
    <section id="overview">
      <h2>1. Overview</h2>
      <h3>Screen layout</h3>
      <table class="tbl">
        <thead><tr><th style="width:18%">Board</th><th>Areas</th><th>Content</th></tr></thead>
        <tbody>
          <tr><td>List board</td><td>Schedule, Tasks, Mail</td><td>Pulled from Google every 10 minutes; only titles and lists are shown. Use each area's "Open" link to view or edit in Google.</td></tr>
          <tr><td>Status board</td><td>Workspaces, Projects, Recent records</td><td>Work status by PC and tool, filled by manual entries and automatic logging.</td></tr>
          <tr><td>Black board</td><td>Notes, AI assistant</td><td>Quick notes written directly in UDeck, and a summary of what needs your attention today.</td></tr>
        </tbody>
      </table>
      <ul>
        <li>Three columns are kept down to a 16-inch laptop width (about 1200px). As the window narrows, the Status board shrinks first.</li>
        <li>Below that, the layout becomes two columns: the List board on the left, the Black board on the top right (notes and AI assistant side by side), and the Status board below it.</li>
        <li>On phones (760px or less), tabs at the top (List board, Status board, Black board) show one board at a time. The last board you viewed is remembered.</li>
      </ul>

      <h3>Where the data comes from</h3>
      <table class="tbl">
        <thead><tr><th style="width:28%">Sender</th><th>What it sends</th><th style="width:22%">When</th></tr></thead>
        <tbody>
          <tr><td>Google Apps Script</td><td>Calendar events, Tasks, Gmail subjects</td><td>Every 10 minutes</td></tr>
          <tr><td>Claude Code hook</td><td>Work folder, last request, start of the last reply</td><td>After every reply</td></tr>
          <tr><td>PC agent</td><td>Online signal, Codex sessions, HANDOFF.md</td><td>Every 15 minutes</td></tr>
          <tr><td>Manual entry on the board</td><td>Work records, projects, notes</td><td>When you enter them</td></tr>
        </tbody>
      </table>
    </section>

    <!-- 2 -->
    <section id="start">
      <h2>2. Getting started</h2>
      <ol class="steps">
        <li><b>Set this browser's PC.</b> Choose the PC you are using in the PC selector at the top. Do this once per PC and per browser. It becomes the default when you add a work record. Leave phones on "Not set".</li>
        <li><b>Review PCs and tools.</b> In <a href="settings.php">Settings</a>, check each PC's name, computer name (the Windows computer name), color and purpose. Add the tools you use.</li>
        <li><b>Add projects.</b> Use "Add project" on the board for each active project. Always fill in the <b>work folder name</b>; automatic logging and HANDOFF.md use it to find the project.</li>
        <li><b>Connect Google.</b> Follow <a href="#google">section 5</a> to connect Apps Script.</li>
        <li><b>Install automatic PC logging.</b> Follow <a href="#agent">section 6</a> and run install.cmd on each PC.</li>
      </ol>
    </section>

    <!-- 3 -->
    <section id="board">
      <h2>3. Using the board</h2>
      <h3>Schedule</h3>
      <ul>
        <li>Switch the range with the "Next 2 days" (today and tomorrow), "This week" and "This month" tabs. Weeks run Monday to Sunday.</li>
        <li>Past events are dimmed, and a red line marks the next event.</li>
        <li>The week and month tabs scroll to today when opened. The browser remembers the last tab you chose.</li>
        <li>Only calendars that are checked (visible) in Google Calendar are included.</li>
      </ul>
      <h3>Tasks</h3>
      <ul>
        <li>Grouped by Google Tasks list. Use the selector at the top right to show a single list.</li>
        <li>Lists and items follow the "My order" sorting in Google Tasks. Subtasks are indented under their parent task (Google sync script v1.1 or later).</li>
        <li>Items that are overdue or due today are shown in red. Mark tasks done in Google Tasks.</li>
      </ul>
      <h3>Mail</h3>
      <ul>
        <li>By default: unread or starred inbox mail from the last 7 days. Starred mail is marked ★.</li>
        <li>Message bodies are not fetched. Use "Open Gmail" to read mail.</li>
        <li>Change the rule with <code>mailQuery</code> in Apps Script. Example, starred only: <code>in:inbox is:starred</code></li>
      </ul>
      <h3>Notes</h3>
      <ul>
        <li>Notes can have several lines. Enter adds a line break; <b>Ctrl + Enter</b> (⌘ + Enter on a Mac) or the Add button adds the note. Line breaks are shown as typed.</li>
        <li>Click a note to edit it. Ctrl + Enter or clicking outside saves; Esc cancels.</li>
        <li>Hover to show Pin, Done and Delete. Pinned notes stay at the top.</li>
      </ul>
      <h3>AI assistant</h3>
      <ul>
        <li>Builds its messages from the data on the board using fixed rules. It does not call any external AI API, so it costs nothing.</li>
        <li>Urgent: overdue tasks and tasks due today, events in the next 3 hours, fixed deadlines within 14 days, self-set deadlines within 3 days, and starred mail.</li>
        <li>Pick up today: your most recent work linked to a project (which PC, tool and project) and that project's next step. If you are on a different PC now, it suggests reading HANDOFF.md first. It also flags active projects with no records for more than 7 days.</li>
        <li>A proverb or a short line appears at the bottom.</li>
        <li>It recalculates every 15 minutes. Click Refresh to recalculate now and change the line.</li>
      </ul>
      <h3>Workspaces</h3>
      <ul>
        <li>Rows are workspaces (PCs or people). The boxes to the right are the projects worked on in that workspace, most recent first. A project worked on in several PCs appears in each of their rows.</li>
        <li>Each box shows the project name, the latest work, the tools used (short-name icons such as Codex, GPT, Claude, Claude C, Gemini, Antigravity), progress (bar and %), the time of the last work and a 7-day activity strip. Records from the last 14 days with no project are grouped in an "Unassigned" box.</li>
        <li>If the boxes do not fit, use the ◀ ▶ buttons next to the title (or scroll sideways). The PC name column stays in place.</li>
        <li>The more recent the work, the stronger the color (half-life of 3 hours). Work within the last hour shows its time in the PC's color.</li>
        <li>Click a box to open details: the latest record for each tool with Edit, Continue logging, Copy folder path, Open Remote Desktop and project assignment.</li>
        <li>For a PC with no records, click its empty box to add one.</li>
      </ul>
      <h3>Projects</h3>
      <ul>
        <li>Columns: rank (#), project, devices (PCs that worked on it; bold is the most recent), tools, progress, next step and deadline.</li>
        <li>Sort by importance, deadline or date added. Your choice is remembered in this browser.</li>
        <li>When sorted by importance, drag the number on the left up or down to change the order. You can also focus the number and use the up and down arrow keys. In every sort order, the number shows the importance rank.</li>
        <li>Progress is the most recent value entered in the project's work records.</li>
        <li>Click a row to edit. Setting the status to "Done" hides it; check "Show done" to see it again.</li>
        <li>The main workspace is the PC where you mostly work on the project, shown under its name as "main: PC name".</li>
        <li>Choose one of three deadline types: No deadline; Self-set (a target date you chose, ochre D-day); Fixed deadline (a date you must meet, such as a submission deadline, red D-day pill). Fixed deadlines also appear under Urgent in the AI assistant from 14 days before.</li>
      </ul>
      <h3>Recent records</h3>
      <p>The last 30 work records from all PCs. Hover to show "Edit"; you can delete from the edit form.</p>
    </section>

    <!-- 4 -->
    <section id="records">
      <h2>4. Work records</h2>
      <h3>Manual entry</h3>
      <p>Click "Add work log" at the top. Enter the PC, tool, project, stage, what you did, progress, next step, work folder and time. A next step you enter also becomes the project's next step.</p>
      <p>Set progress by dragging the bar or typing a number (0 to 100). Choosing a project fills in its latest progress. Click "Clear" to record no progress.</p>
      <h3>Automatic vs. manual</h3>
      <table class="tbl">
        <thead><tr><th style="width:34%">Tool</th><th>How it is recorded</th></tr></thead>
        <tbody>
          <tr><td>Claude Code app</td><td>Automatic, after every reply.</td></tr>
          <tr><td>Codex in the ChatGPT app</td><td>Automatic. Changed sessions are recorded every 15 minutes.</td></tr>
          <tr><td>Regular chats in the ChatGPT app</td><td>These live only on OpenAI's servers and cannot be read automatically. Use HANDOFF.md or a manual entry.</td></tr>
          <tr><td>AI on the web, Perplexity, etc.</td><td>Manual entry.</td></tr>
        </tbody>
      </table>
      <h3>Automatic project assignment</h3>
      <p>UDeck walks the work folder path <b>from the innermost folder outward</b> and picks the first project whose work folder name matches.</p>
      <table class="tbl">
        <thead><tr><th>Work folder</th><th>Project work folder name</th><th>Result</th></tr></thead>
        <tbody>
          <tr><td><code>D:\Work\Grant_2027\docs</code></td><td><code>Grant_2027</code></td><td>Grant project</td></tr>
          <tr><td><code>E:\Projects\Research\Gait_DL\src</code></td><td><code>Research/Gait_DL</code></td><td>Multi-level names also work</td></tr>
          <tr><td><code>D:\Work</code></td><td><code>Work</code></td><td>Top-level project (does not take records of sub-projects)</td></tr>
        </tbody>
      </table>
      <p>With no match, the record is "Unassigned". Assign a project once from the details of the workspace's "Unassigned" box and that folder name is saved to the project, so later records from the same folder are assigned automatically. Using only the folder name keeps projects matched even when paths differ between PCs.</p>
    </section>

    <!-- 5 -->
    <section id="google">
      <h2>5. Google sync (Apps Script)</h2>
      <p>Apps Script reads your schedule, tasks and mail every 10 minutes with your Google account's permission and sends them to UDeck. No Google password or token is stored on the server.</p>
      <h3>Setup</h3>
      <ol class="steps">
        <li>In <a href="settings.php#h-token">Settings → Connection keys</a>, create a key named <code>Apps Script</code> with purpose <b>Google sync</b>, and copy it. The key is shown only once.</li>
        <li>At <a href="https://script.google.com" target="_blank" rel="noopener">script.google.com</a>, create a <b>New project</b>. First check that the profile at the top right is the account whose data you want.</li>
        <li>Replace the default code with the code below. In <code>send</code> at the top, choose what this account sends.</li>
        <li>Next to "Services" on the left, click <b>+</b> and add <b>Google Tasks API</b>.</li>
        <li>In the gear icon (Project Settings), set the time zone to <b>Seoul</b>, and add the two values under <b>Script Properties</b> at the bottom.</li>
        <li>Save with <code>Ctrl + S</code>. Pick <code>testSync</code> in the function list at the top and click ▷ Run.</li>
        <li>In the authorization dialog, choose your account. If "Google hasn't verified this app" appears, click <b>Advanced → Go to UDeck Sync</b> and Allow. This appears because you wrote the script yourself.</li>
        <li>If the execution log shows <code>"ok":true</code> with your account and item counts, it worked.</li>
        <li>Run <code>installTrigger</code> to start sending every 10 minutes. Use <code>removeTrigger</code> to stop.</li>
      </ol>
      <?= code("UDECK_URL\t{$ingestUrl}\nUDECK_TOKEN\tudk_... (the key from step 1)", 'Script properties') ?>
      <?php if ($gasCode): ?>
        <details class="gas">
          <summary>Show the Apps Script code (UDeck_Sync.gs)</summary>
          <?= code($gasCode, 'UDeck_Sync.gs') ?>
        </details>
      <?php else: ?>
        <p class="note">The code is in <code>UDeck\gas\UDeck_Sync.gs</code> in your synced folder.</p>
      <?php endif; ?>

      <h3>Using separate accounts</h3>
      <p>Apps Script only reads data of the account that created it, and the account cannot be changed later. If mail, calendar and tasks are in different accounts, create <b>one project per account</b> with a different <code>send</code>. UDeck stores data per account, so they never overwrite each other.</p>
      <?= code("// Personal Gmail account: mail only\nsend: { events: false, tasks: false, mails: true },\n\n// School account: events and tasks\nsend: { events: true, tasks: true, mails: false },", 'CONFIG.send examples') ?>
      <p>School or company Workspace accounts may be blocked by admin policy. In that case, share the school calendar with your personal account or forward the mail, and collect it with the personal account's script.</p>

      <h3>Main options (CONFIG at the top of the code)</h3>
      <table class="tbl">
        <thead><tr><th style="width:22%">Option</th><th style="width:22%">Default</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>send</code></td><td>all true</td><td>What this account sends</td></tr>
          <tr><td><code>eventPadDays</code></td><td>7</td><td>Extra days sent before and after this month</td></tr>
          <tr><td><code>mailQuery</code></td><td>unread or starred, last 7 days</td><td>Same syntax as the Gmail search box</td></tr>
          <tr><td><code>mailMax</code></td><td>30</td><td>Number of mail threads to send</td></tr>
          <tr><td><code>excludeCalendars</code></td><td>none</td><td>Calendar names to skip, e.g. <code>['Holidays in South Korea']</code></td></tr>
        </tbody>
      </table>
      <h3>Updating the script</h3>
      <p>When a UDeck update includes a new Apps Script (noted in the changelog), replace the code in each account that sends data.</p>
      <ol class="steps">
        <li>Sign in to <a href="https://script.google.com" target="_blank" rel="noopener">script.google.com</a> with that Google account and open the UDeck sync project.</li>
        <li>Open <code>Code.gs</code> and note the <code>send</code> value in <code>CONFIG</code> at the top.</li>
        <li>Click in the editor, press <code>Ctrl + A</code> then <code>Delete</code>, and paste the new code (see "Show the Apps Script code" above).</li>
        <li>Set <code>send</code> back to the value you noted and save with <code>Ctrl + S</code>. Check that the version on the second line changed.</li>
        <li>Run <code>testSync</code> and check that the log shows <code>"ok":true</code>. Allow the permissions again if asked.</li>
      </ol>
      <p class="note">Script properties (<code>UDECK_URL</code>, <code>UDECK_TOKEN</code>) and the 10-minute trigger are stored apart from the code, so you do not need to set them again.</p>
      <p>If nothing is collected, run <code>diagnoseUDeck</code> to see the account in use, each calendar's visibility and event count, task counts per list, and mail counts.</p>
    </section>

    <!-- 6 -->
    <section id="agent">
      <h2>6. Automatic PC logging (install.cmd)</h2>
      <p>Small scripts in <code>UDeck\agent</code> in your synced folder. They do not open windows or keep running; they run for a few seconds only when needed. Install once per PC.</p>
      <table class="tbl">
        <thead><tr><th style="width:26%">File</th><th>Purpose</th></tr></thead>
        <tbody>
          <tr><td><code>install.cmd</code></td><td>Install (double-click)</td></tr>
          <tr><td><code>status.cmd</code></td><td>Status and a preview of what would be sent</td></tr>
          <tr><td><code>uninstall.cmd</code></td><td>Remove</td></tr>
          <tr><td><code>udeck_hook.py</code></td><td>Runs after every Claude Code reply</td></tr>
          <tr><td><code>udeck_agent.py</code></td><td>Runs every 15 minutes via Task Scheduler</td></tr>
        </tbody>
      </table>
      <p>Install on each PC. The whole <code>agent</code> folder is needed, not just install.cmd. If the folder is synced by Syncthing, run install.cmd from the synced copy on each PC.</p>

      <h3>Installation</h3>
      <ol class="steps">
        <li>Requirement: Python 3.8 or later.</li>
        <li>In <a href="settings.php#h-token">Settings → Connection keys</a>, create a key named like <code>Laptop auto logging</code> with purpose <b>PC work logging</b> and that <b>PC</b> selected, then click <b>Copy key</b>. Create a separate key for each PC.</li>
        <li>Double-click <code>UDeck\agent\install.cmd</code>. It reads the key from the clipboard automatically.</li>
        <li>It runs a connection test, adds the Claude Code hook, registers the scheduled task, runs once and sends a test record.</li>
        <li>If the test record appears in that PC's row on the board (with the Claude C icon), it worked. Delete the test record via Edit → Delete in Recent records.</li>
        <li>Restart any open Claude Code app so automatic logging takes effect.</li>
      </ol>

      <h3>What is and is not sent</h3>
      <ul>
        <li>Sent: PC, tool, session ID, work folder path, the first 500 characters of the last request, the first 300 characters of the last reply, time, and HANDOFF.md contents.</li>
        <li>Not sent: source code, data files, full AI replies or whole conversations.</li>
        <li>On first install, only Codex sessions changed in the last 24 hours are sent.</li>
        <li>Records created while offline are kept and sent on the next 15-minute run.</li>
      </ul>

      <h3>Commands (PowerShell)</h3>
      <?= code("# Status\npython D:\\Work\\UDeck\\agent\\udeck_setup.py status\n\n# Preview what would be sent (last 7 days, nothing is sent)\npython D:\\Work\\UDeck\\agent\\udeck_setup.py diagnose\n\n# Show, add or remove HANDOFF.md watch folders\npython D:\\Work\\UDeck\\agent\\udeck_setup.py watch\npython D:\\Work\\UDeck\\agent\\udeck_setup.py watch D:\\Projects\npython D:\\Work\\UDeck\\agent\\udeck_setup.py watch --remove D:\\Projects\n\n# Uninstall (add --purge to also delete settings)\npython D:\\Work\\UDeck\\agent\\udeck_setup.py uninstall", 'Adjust the path to the synced folder on that PC') ?>

      <h3>Per-PC settings file</h3>
      <p>The connection key and send state are stored in <code>%LOCALAPPDATA%\UDeck\config.json</code>, not in the synced folder, so PCs never mix. Microsoft Store Python may store this file under <code>%LOCALAPPDATA%\Packages\PythonSoftwareFoundation.Python…\LocalCache\Local\UDeck\</code>.</p>
      <table class="tbl">
        <thead><tr><th style="width:22%">Option</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>watch_roots</code></td><td>Folders searched for HANDOFF.md. Default: two levels above the agent folder (e.g. D:\Work). The <code>watch</code> command is the easiest way to change it.</td></tr>
          <tr><td><code>watch_depth</code></td><td>Search depth. Default 3</td></tr>
          <tr><td><code>codex_tool</code>, <code>claude_tool</code></td><td>UDeck tool codes to record under. Defaults <code>chatgpt_app</code>, <code>claude_code</code></td></tr>
        </tbody>
      </table>
    </section>

    <!-- 7 -->
    <section id="handoff">
      <h2>7. HANDOFF.md</h2>
      <p>A handoff note kept in the project folder. When you move to another PC or AI tool, this one file carries the context. Changes reach UDeck within 15 minutes.</p>
      <h3>Requirements</h3>
      <ul>
        <li>Location: within 3 levels under a watch folder. Example: <code>D:\Work\Grant_2027\HANDOFF.md</code></li>
        <li>Link: a folder name in its path must match a project's work folder name (<a href="#records">rules in section 4</a>). With no matching project, it is not sent and is checked again every 6 hours.</li>
        <li>File name: <code>HANDOFF.md</code> (any case). Syncthing conflict files (<code>.sync-conflict</code>) are ignored.</li>
      </ul>
      <h3>Where it shows up</h3>
      <ul>
        <li>The project's <b>next step</b>: a <code>Next step: …</code> line, or the first item under a <code>## Next steps</code> heading. The Korean markers <code>다음 할 일: …</code> and <code>## 다음 할 일</code> work too.</li>
        <li>The bottom of the project edit form: the full HANDOFF.md and when it was updated.</li>
      </ul>
      <h3>Example</h3>
      <?= code("# HANDOFF\n## 현재 상태\nSecond revision of the literature review done. Organizing references.\n\n## 다음 할 일\n- Draft the budget table, then compile researcher CVs\n\n## 메모\n- Focus on papers from the last 5 years\n- Last worked on: Laptop, Claude Code", 'HANDOFF.md') ?>
      <h3>Letting AI keep it updated</h3>
      <p>Add the following to <code>CLAUDE.md</code> (Claude Code) and <code>AGENTS.md</code> (Codex) in the project folder. The AI then reads it at the start and updates it at the end of each session.</p>
      <?= code("## Work handoff\n- At the start of a session, read HANDOFF.md first and check the current status and next step.\n- At the end of a session, update \"현재 상태\" (current status) and \"다음 할 일\" (next step) in HANDOFF.md.\n- Put the single next thing to do as the first item under \"다음 할 일\".\n- Note the PC and tool you last worked on under 메모.", 'Add to CLAUDE.md / AGENTS.md') ?>
      <p>For work that is not logged automatically, such as regular ChatGPT chats, ask the AI at the end to "summarize this into HANDOFF.md", then paste the result into the file.</p>
      <h3>In a top-level folder</h3>
      <p>You can also place it in a top-level folder such as <code>Projects</code> or <code>Work</code>. Create an "Overview" project whose work folder name is that folder, and make sure the folder is inside a watch folder (add it with <code>watch</code> if needed). The top-level HANDOFF.md applies only to that project; sub-projects follow their own HANDOFF.md. The default is one HANDOFF.md in each project folder.</p>
    </section>

    <!-- 8 -->
    <section id="settings">
      <h2>8. Settings</h2>
      <table class="tbl">
        <thead><tr><th style="width:22%">Area</th><th>What you can do</th></tr></thead>
        <tbody>
          <tr><td>Language</td><td>Choose Korean or English. Applies to all devices.</td></tr>
          <tr><td>Workspaces (PCs or people)</td><td>Change code, name, computer name, color, purpose and order. A workspace can also be the person responsible for the work instead of a PC. Add with the blank last row; tick "Delete" to remove. Deleting a workspace also deletes its work records. If you change a code, update the automatic logging settings for that PC (or person) too.</td></tr>
          <tr><td>Tools</td><td>Add, delete, rename or recode. Tools with "Use" on appear in the log form. In workspace boxes and the project list they appear with their icon label (up to 12 characters). If left blank, the label is set from the name (Codex, GPT, Claude, Claude C, Gemini or Antigravity), or the display name is used.</td></tr>
          <tr><td>Connection keys</td><td>Create and revoke keys for Apps Script (Google sync) and for PCs (PC work logging). A key is shown only right after it is created.</td></tr>
          <tr><td>Change password</td><td>Other devices must log in again afterwards.</td></tr>
          <tr><td>Received data log</td><td>The last 20 transmissions from automatic logging and Google sync. Check here first when something seems missing.</td></tr>
        </tbody>
      </table>
    </section>

    <!-- 9 -->
    <section id="trouble">
      <h2>9. Troubleshooting</h2>
      <table class="tbl">
        <thead><tr><th style="width:34%">Symptom</th><th>What to check</th></tr></thead>
        <tbody>
          <tr><td>"Google" at the top unchanged for over 30 minutes</td><td>Apps Script → Executions on the left: look for failures and error messages</td></tr>
          <tr><td>No events, tasks or mail</td><td>The account running Apps Script; run <code>diagnoseUDeck</code></td></tr>
          <tr><td>Week and month tabs only show today and tomorrow</td><td>The Apps Script code is an old version. Replace it with the new code</td></tr>
          <tr><td>Claude Code work is not recorded</td><td>Restart the Claude Code app; check that <code>status.cmd</code> shows the hook as installed</td></tr>
          <tr><td>A PC never shows "online"</td><td>In Task Scheduler, the last run result of "UDeck Agent" should be 0x0</td></tr>
          <tr><td>Connection failed, HTTP 401</td><td>The key is wrong or revoked. Create a new key, copy it, and run install.cmd again</td></tr>
          <tr><td>HTTP 403</td><td>Wrong key purpose. Google sync keys and PC work logging keys are not interchangeable</td></tr>
          <tr><td>Work appears as "Unassigned"</td><td>Check the project's work folder name, or assign the project once from the details of the "Unassigned" box</td></tr>
          <tr><td>HANDOFF.md is not reflected</td><td>Is it inside a watch folder (<code>watch</code>)? Does the folder name match a project?</td></tr>
          <tr><td>"The server could not process this"</td><td>Check <code>udeck_private/data/php_error.log</code> on the server</td></tr>
          <tr><td>Keeps logging out</td><td>Tick "Keep me logged in" when logging in; check the <code>udeck_private/sessions</code> folder on the server</td></tr>
        </tbody>
      </table>
    </section>

    <!-- 10 -->
    <section id="backup">
      <h2>10. Backup and security</h2>
      <ul>
        <li>All data is in one file on the server: <code>udeck_private/data/udeck.sqlite</code>. Downloading it is a full backup.</li>
        <li>When uploading new code, do not overwrite or delete the <code>udeck_private/data</code> and <code>sessions</code> folders.</li>
        <li>Five wrong passwords within 15 minutes locks login for 15 minutes. "Keep me logged in" lasts 14 days.</li>
        <li>Connection keys are stored on the server only as hashes. If you replace or lose a PC, revoke its key in Settings.</li>
        <li>Mail bodies, source code and full conversations are never stored in UDeck.</li>
      </ul>
      <p class="muted">UDeck <?= e(UDECK_VERSION) ?></p>
    </section>
  </article>
</div>
