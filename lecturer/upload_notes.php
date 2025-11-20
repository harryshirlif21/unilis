<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lecturer Notes Creator</title>
    <style>
        :root{
            --topic-color: #1f6feb;
            --subtopic-color: #0ea5a4;
            --file-label-color: #f59e0b;
            --image-label-color: #ef4444;
        }
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { display: flex; gap: 20px; }
        .form-section, .preview-section {
            background: white; padding: 20px; border-radius: 10px; width: 50%; overflow-y: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .topic-block { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .subtopic-block { margin-left: 20px; margin-top: 10px; padding: 10px; border-left: 3px solid #888; }
        input[type="text"], textarea, select { width: 100%; margin-top: 8px; padding: 8px; box-sizing: border-box; }
        button { padding: 8px 15px; margin-top: 10px; cursor: pointer; }
        .label-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; color: white; font-size: 12px; margin-right:8px; }
        .topic-pill { background: var(--topic-color); }
        .subtopic-pill { background: var(--subtopic-color); }
        .file-pill { background: var(--file-label-color); }
        .image-pill { background: var(--image-label-color); }
        #imagePreview img { width: 150px; margin:5px; border-radius:8px; }
        .color-row { display:flex; gap:10px; align-items:center; margin-top:10px; }
        .small { width:120px; }
        .choice-row { display:flex; gap:8px; align-items:center; margin-top:6px; }
        .remove-btn { background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:6px; cursor:pointer; }
    </style>
</head>
<body>
    <h1>Create Lecturer Notes</h1>

    <div class="container">
        <!-- Form Section -->
        <div class="form-section">
            <h2>Input Notes</h2>

            <div class="color-row">
                <label>Topic Color <input type="color" id="topicColor" value="#1f6feb" onchange="updateColors()" class="small"></label>
                <label>Subtopic Color <input type="color" id="subtopicColor" value="#0ea5a4" onchange="updateColors()" class="small"></label>
                <label>File Label Color <input type="color" id="fileLabelColor" value="#f59e0b" onchange="updateColors()" class="small"></label>
                <label>Image Label Color <input type="color" id="imageLabelColor" value="#ef4444" onchange="updateColors()" class="small"></label>
            </div>

            <div id="topics"></div>
            <button onclick="addTopic()">+ Add Topic</button>
            <hr>
            <h3>Upload Files</h3>
            <input type="file" multiple id="fileUploads" onchange="updateFiles()">
            <div id="fileList"></div>
            <h3>Upload Images</h3>
            <input type="file" accept="image/*" multiple id="imageUploads" onchange="updateImages()">
        </div>

        <!-- Preview Section -->
        <div class="preview-section">
            <h2>Live Preview</h2>
            <div id="preview"></div>

            <h3>Files</h3>
            <ul id="filePreview"></ul>

            <h3>Images</h3>
            <div id="imagePreview"></div>
        </div>
    </div>

<script>
let topics = [];

// --- Color handling ---
function updateColors(){
    const t = document.getElementById('topicColor').value;
    const s = document.getElementById('subtopicColor').value;
    const f = document.getElementById('fileLabelColor').value;
    const i = document.getElementById('imageLabelColor').value;
    document.documentElement.style.setProperty('--topic-color', t);
    document.documentElement.style.setProperty('--subtopic-color', s);
    document.documentElement.style.setProperty('--file-label-color', f);
    document.documentElement.style.setProperty('--image-label-color', i);
}

function addTopic() {
    const id = Date.now() + Math.floor(Math.random()*1000);
    topics.push({ id, title: "", label: "", subtopics: [] });
    renderForm();
}

function removeTopic(topicId){
    topics = topics.filter(t=>t.id!==topicId);
    renderForm();
}

function addSubtopic(topicId) {
    const topic = topics.find(t => t.id === topicId);
    topic.subtopics.push({ id: Date.now() + Math.floor(Math.random()*1000), title: "", questions: "", choices: [], correctChoice: null, label: "" });
    renderForm();
}

function removeSubtopic(topicId, subId){
    const topic = topics.find(t=>t.id===topicId);
    topic.subtopics = topic.subtopics.filter(s=>s.id!==subId);
    renderForm();
}

function updateTopicTitle(id, value) {
    const t = topics.find(t => t.id === id);
    if(t) t.title = value;
    renderPreview();
}

function updateTopicLabel(id, value){
    const t = topics.find(t => t.id === id);
    if(t) t.label = value;
    renderPreview();
}

function updateSubtopicTitle(topicId, subId, value) {
    const topic = topics.find(t => t.id === topicId);
    const sub = topic.subtopics.find(s => s.id === subId);
    if(sub) sub.title = value;
    renderPreview();
}

function updateSubtopicLabel(topicId, subId, value){
    const topic = topics.find(t => t.id === topicId);
    const sub = topic.subtopics.find(s => s.id === subId);
    if(sub) sub.label = value;
    renderPreview();
}

function updateQuestions(topicId, subId, value) {
    const topic = topics.find(t => t.id === topicId);
    const sub = topic.subtopics.find(s => s.id === subId);
    if(sub) sub.questions = value;
    renderPreview();
}

// Choices
function addChoice(topicId, subId){
    const topic = topics.find(t=>t.id===topicId);
    const sub = topic.subtopics.find(s=>s.id===subId);
    sub.choices.push({id:Date.now()+Math.floor(Math.random()*1000), text: ""});
    renderForm();
}

function updateChoice(topicId, subId, choiceId, value){
    const topic = topics.find(t=>t.id===topicId);
    const sub = topic.subtopics.find(s=>s.id===subId);
    const c = sub.choices.find(ch=>ch.id===choiceId);
    if(c) c.text = value;
    renderPreview();
}

function removeChoice(topicId, subId, choiceId){
    const topic = topics.find(t=>t.id===topicId);
    const sub = topic.subtopics.find(s=>s.id===subId);
    sub.choices = sub.choices.filter(c=>c.id!==choiceId);
    if(sub.correctChoice===choiceId) sub.correctChoice = null;
    renderForm();
}

function setCorrectChoice(topicId, subId, choiceId){
    const topic = topics.find(t=>t.id===topicId);
    const sub = topic.subtopics.find(s=>s.id===subId);
    sub.correctChoice = choiceId;
    renderPreview();
}

// File uploads and labels
let lastFiles = [];
function updateFiles(){
    const files = Array.from(document.getElementById('fileUploads').files);
    lastFiles = files.map((f,idx)=>({id: Date.now()+idx, file:f, label: ''}));
    renderFileInputs();
    renderFilePreview();
}

function renderFileInputs(){
    const container = document.getElementById('fileList');
    container.innerHTML = '';
    lastFiles.forEach((f,idx)=>{
        const div = document.createElement('div');
        div.innerHTML = `
            <div style="margin-top:8px;">
                <strong>${f.file.name}</strong><br>
                <input type='text' placeholder='Label for this file' style="color:var(--file-label-color)" oninput="updateFileLabel(${f.id}, this.value)">
                <button onclick="removeFile(${f.id})" class='remove-btn'>Remove</button>
            </div>
        `;
        container.appendChild(div);
    });
}

function updateFileLabel(id, value){
    const f = lastFiles.find(x=>x.id===id);
    if(f) f.label = value;
    renderFilePreview();
}

function removeFile(id){
    lastFiles = lastFiles.filter(x=>x.id!==id);
    renderFileInputs();
    renderFilePreview();
}

function renderFilePreview(){
    const ul = document.getElementById('filePreview');
    ul.innerHTML = '';
    lastFiles.forEach(f=>{
        const li = document.createElement('li');
        li.innerHTML = `<span class='file-pill label-pill'>${f.label || 'File'}</span> ${f.file.name}`;
        ul.appendChild(li);
    });
}

// Images
let lastImages = [];
function updateImages(){
    const images = Array.from(document.getElementById('imageUploads').files);
    lastImages = images.map((img,idx)=>({id: Date.now()+idx, file: img, label: ''}));
    renderImagePreview();
    renderImageInputs();
}

function renderImageInputs(){
    // reuse fileList area for labels if desired - or keep separate UI; here we add labels inline under images
}

function updateImageLabel(id, value){
    const im = lastImages.find(x=>x.id===id);
    if(im) im.label = value;
    renderImagePreview();
}

function renderImagePreview(){
    const container = document.getElementById('imagePreview');
    container.innerHTML = '';
    lastImages.forEach(imgObj => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrapper = document.createElement('div');
            wrapper.style.display = 'inline-block';
            wrapper.style.margin = '6px';
            const label = `<div><span class='image-pill label-pill'>${imgObj.label||'Image'}</span></div>`;
            wrapper.innerHTML = `<img src='${e.target.result}'><br>${label}`;
            container.appendChild(wrapper);
        };
        reader.readAsDataURL(imgObj.file);
    });
}

// --- Rendering form & preview ---
function renderForm() {
    const container = document.getElementById('topics');
    container.innerHTML = '';

    topics.forEach(topic => {
        const topicHTML = document.createElement('div');
        topicHTML.className = 'topic-block';
        topicHTML.innerHTML = `
            <div style='display:flex;justify-content:space-between;align-items:center'>
                <div>
                    <label class='topic-pill label-pill'>Topic</label>
                    <input type='text' style="color:var(--topic-color)" placeholder='Topic title' value='${escapeHtml(topic.title)}' oninput="updateTopicTitle(${topic.id}, this.value)">
                    <input type='text' placeholder='Topic label (optional)' value='${escapeHtml(topic.label)}' oninput="updateTopicLabel(${topic.id}, this.value)">
                </div>
                <div>
                    <button onclick="addSubtopic(${topic.id})">+ Subtopic</button>
                    <button onclick="removeTopic(${topic.id})" class='remove-btn'>Delete Topic</button>
                </div>
            </div>
            <div id='subs-${topic.id}'></div>
        `;
        container.appendChild(topicHTML);

        const subsDiv = document.getElementById(`subs-${topic.id}`);
        topic.subtopics.forEach(sub => {
            const d = document.createElement('div');
            d.className = 'subtopic-block';
            d.innerHTML = `
                <div style='display:flex;justify-content:space-between;align-items:center'>
                    <div style='flex:1'>
                        <label class='subtopic-pill label-pill'>Subtopic</label>
                        <input type='text' style="color:var(--subtopic-color)" placeholder='Subtopic title' value='${escapeHtml(sub.title)}' oninput="updateSubtopicTitle(${topic.id}, ${sub.id}, this.value)">
                        <input type='text' placeholder='Subtopic label (optional)' value='${escapeHtml(sub.label)}' oninput="updateSubtopicLabel(${topic.id}, ${sub.id}, this.value)">
                    </div>
                    <div>
                        <button onclick="addChoice(${topic.id}, ${sub.id})">+ Choice</button>
                        <button onclick="removeSubtopic(${topic.id}, ${sub.id})" class='remove-btn'>Delete Subtopic</button>
                    </div>
                </div>
                <label>Question (short prompt):</label>
                <textarea rows='2' oninput="updateQuestions(${topic.id}, ${sub.id}, this.value)">${escapeHtml(sub.questions)}</textarea>
                <div id='choices-${sub.id}'></div>
            `;
            subsDiv.appendChild(d);

            // render choices
            const choicesDiv = document.getElementById(`choices-${sub.id}`);
            choicesDiv.innerHTML = '';
            sub.choices.forEach(choice => {
                const row = document.createElement('div');
                row.className = 'choice-row';
                row.innerHTML = `
                    <input type='radio' name='correct-${sub.id}' ${sub.correctChoice===choice.id? 'checked' : ''} onclick="setCorrectChoice(${topic.id}, ${sub.id}, ${choice.id})">
                    <input type='text' placeholder='Choice text' value='${escapeHtml(choice.text)}' oninput="updateChoice(${topic.id}, ${sub.id}, ${choice.id}, this.value)">
                    <button onclick="removeChoice(${topic.id}, ${sub.id}, ${choice.id})" class='remove-btn'>Remove</button>
                `;
                choicesDiv.appendChild(row);
            });
        });
    });

    renderPreview();
}

function renderPreview() {
    const preview = document.getElementById('preview');
    preview.innerHTML = topics.map(topic => `
        <div style='margin-bottom:14px;'>
            <div style="font-family: Georgia, serif; font-size:20px; color: var(--topic-color); font-weight:bold;">
                ${escapeHtml(topic.title)}
            </div>
            ${topic.subtopics.map(sub => `
                <div style='margin-left:15px;margin-top:10px;'>
                    <div style="font-family: 'Trebuchet MS', sans-serif; font-size:16px; color: var(--subtopic-color); font-weight:600;">
                        ${escapeHtml(sub.title)}
                    </div>
                    <div style="font-family: 'Calibri', sans-serif; font-size:14px; margin-top:4px;">
                        <span style="font-weight:600;">Question:</span>
                        <span style="color:#555;"> ${escapeHtml(sub.questions)}</span>
                    </div>
                    <div style="margin-left:10px; margin-top:6px; font-family: Arial, sans-serif;">
                        ${sub.choices.map(c=>`<div style='margin-top:4px; color:#333;'>
                            <label>
                                <input type='radio' disabled ${sub.correctChoice===c.id? 'checked' : ''}>
                                <span style="font-style:italic;">${escapeHtml(c.text)}</span>
                            </label>
                        </div>`).join('')}
                    </div>
                </div>
            `).join('')}
        </div>
    `).join('');

    renderFilePreview();
    renderImagePreview();
}();
    renderImagePreview();
}

function escapeHtml(unsafe){
    if(!unsafe && unsafe !== 0) return '';
    return String(unsafe).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,'&quot;');
}

// initialize
updateColors();
renderForm();
</script>

</body>
</html>
