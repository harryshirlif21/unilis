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
    .topic-block { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 8px; position: relative; }
    .subtopic-block { margin-left: 20px; margin-top: 10px; padding: 10px; border-left: 3px solid #888; position: relative; }
    input[type="text"], textarea, select { width: 100%; margin-top: 8px; padding: 8px; box-sizing: border-box; }
    button { padding: 6px 12px; margin-top: 10px; cursor: pointer; }
    .label-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; color: white; font-size: 12px; margin-right:8px; }
    .topic-pill { background: var(--topic-color); }
    .subtopic-pill { background: var(--subtopic-color); }
    .file-pill { background: var(--file-label-color); }
    .image-pill { background: var(--image-label-color); }
    #imagePreview img { width: 150px; margin:5px; border-radius:8px; }
    .choice-row { display:flex; gap:8px; align-items:center; margin-top:6px; }
    .remove-btn { background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:6px; cursor:pointer; }
    .edit-btn { background:#10b981; color:white; border:none; padding:4px 8px; border-radius:6px; cursor:pointer; margin-left:5px;}
    .unit-select { margin-bottom:15px; }
</style>
</head>
<body>
<h1>Lecturer Notes Creator</h1>

<div class="unit-select">
    <label>Select Unit: 
        <select id="unitDropdown" onchange="loadUnitTopics()">
            <option value="">-- Select Unit --</option>
        </select>
    </label>
</div>

<div class="container">
    <div class="form-section">
        <h2>Input Notes</h2>
        <div id="topics"></div>
        <button onclick="addTopic()">+ Add Topic</button>
        <hr>
        <h3>Upload Files</h3>
        <input type="file" multiple id="fileUploads" onchange="updateFiles()">
        <div id="fileList"></div>
        <h3>Upload Images</h3>
        <input type="file" accept="image/*" multiple id="imageUploads" onchange="updateImages()">
    </div>

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
let lastFiles = [];
let lastImages = [];
let units = []; // This should come from backend

// --- Mock backend units data (replace with AJAX call to fetch) ---
units = [
    {id:1, name:"Mathematics"},
    {id:2, name:"Physics"},
    {id:3, name:"Computer Science"}
];
const unitDropdown = document.getElementById('unitDropdown');
units.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u.id;
    opt.textContent = u.name;
    unitDropdown.appendChild(opt);
});

// --- Load topics for selected unit ---
function loadUnitTopics(){
    const unitId = unitDropdown.value;
    if(!unitId) { topics = []; renderForm(); return; }
    // MOCK: Fetch topics from backend for unitId (replace with AJAX)
    // Here just clear existing topics
    topics = [];
    renderForm();
}

// --- Topic & Subtopic Functions ---
function addTopic() {
    const id = Date.now() + Math.floor(Math.random()*1000);
    topics.push({ id, title: "", subtopics: [] });
    renderForm();
}

function removeTopic(topicId){ topics = topics.filter(t=>t.id!==topicId); renderForm(); }
function addSubtopic(topicId){
    const topic = topics.find(t => t.id === topicId);
    topic.subtopics.push({ id: Date.now(), title: "", questions:"", choices: [], correctChoice:null });
    renderForm();
}
function removeSubtopic(topicId, subId){
    const topic = topics.find(t => t.id === topicId);
    topic.subtopics = topic.subtopics.filter(s=>s.id!==subId);
    renderForm();
}

function updateTopicTitle(id, value){ const t = topics.find(t=>t.id===id); if(t) t.title=value; renderPreview(); }
function updateSubtopicTitle(topicId, subId, value){ const t = topics.find(t=>t.id===topicId); const s = t.subtopics.find(x=>x.id===subId); if(s) s.title=value; renderPreview(); }
function updateQuestions(topicId, subId, value){ const t = topics.find(t=>t.id===topicId); const s = t.subtopics.find(x=>x.id===subId); if(s) s.questions=value; renderPreview(); }

// Choices
function addChoice(topicId, subId){ const t = topics.find(t=>t.id===topicId); const s = t.subtopics.find(x=>x.id===subId); s.choices.push({id:Date.now(), text:""}); renderForm(); }
function updateChoice(topicId, subId, choiceId, value){ const t = topics.find(t=>t.id===topicId); const s = t.subtopics.find(x=>x.id===subId); const c = s.choices.find(ch=>ch.id===choiceId); if(c) c.text=value; renderPreview(); }
function removeChoice(topicId, subId, choiceId){ const t = topics.find(t=>t.id===topicId); const s = t.subtopics.find(x=>x.id===subId); s.choices = s.choices.filter(c=>c.id!==choiceId); if(s.correctChoice===choiceId) s.correctChoice=null; renderForm(); }
function setCorrectChoice(topicId, subId, choiceId){ const t = topics.find(t=>t.id===topicId); const s = t.subtopics.find(x=>x.id===subId); s.correctChoice=choiceId; renderPreview(); }

// --- File & Image Functions ---
function updateFiles(){ 
    const files = Array.from(document.getElementById('fileUploads').files); 
    lastFiles = files.map((f,i)=>({id:Date.now()+i, file:f, label:""}));
    renderFileInputs(); renderFilePreview(); 
}
function renderFileInputs(){ 
    const container = document.getElementById('fileList'); container.innerHTML=""; 
    lastFiles.forEach(f=>{ 
        const div = document.createElement('div'); 
        div.innerHTML = `<div style="margin-top:8px;"><strong>${f.file.name}</strong><br><input type='text' placeholder='Label for this file' style="color:var(--file-label-color)" oninput="updateFileLabel(${f.id}, this.value)"><button onclick="removeFile(${f.id})" class='remove-btn'>Remove</button></div>`; 
        container.appendChild(div); 
    }); 
}
function updateFileLabel(id,val){ const f = lastFiles.find(x=>x.id===id); if(f) f.label=val; renderFilePreview(); }
function removeFile(id){ lastFiles = lastFiles.filter(x=>x.id!==id); renderFileInputs(); renderFilePreview(); }
function renderFilePreview(){ const ul=document.getElementById('filePreview'); ul.innerHTML=""; lastFiles.forEach(f=>{ const li=document.createElement('li'); li.innerHTML=`<span class='file-pill label-pill'>${f.label||'File'}</span> ${f.file.name}`; ul.appendChild(li); }); }

function updateImages(){ 
    const images = Array.from(document.getElementById('imageUploads').files); 
    lastImages = images.map((img,i)=>({id:Date.now()+i, file:img, label:""})); 
    renderImagePreview(); 
}
function renderImagePreview(){ 
    const container=document.getElementById('imagePreview'); container.innerHTML=""; 
    lastImages.forEach(imgObj=>{ 
        const reader = new FileReader(); 
        reader.onload=e=>{ 
            const wrapper=document.createElement('div'); wrapper.style.display='inline-block'; wrapper.style.margin='6px'; 
            wrapper.innerHTML=`<img src='${e.target.result}'><br><span class='image-pill label-pill'>${imgObj.label||'Image'}</span>`; 
            container.appendChild(wrapper); 
        }; 
        reader.readAsDataURL(imgObj.file); 
    }); 
}

// --- Render Form & Preview ---
function renderForm(){
    const container=document.getElementById('topics'); container.innerHTML="";
    topics.forEach(topic=>{
        const div=document.createElement('div'); div.className='topic-block';
        div.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <label class='topic-pill label-pill'>Topic</label>
                <input type='text' style="color:var(--topic-color)" placeholder='Topic title' value='${escapeHtml(topic.title)}' oninput="updateTopicTitle(${topic.id},this.value)">
            </div>
            <div>
                <button onclick="addSubtopic(${topic.id})">+ Subtopic</button>
                <button onclick="removeTopic(${topic.id})" class='remove-btn'>Delete</button>
            </div>
        </div>
        <div id='subs-${topic.id}'></div>`;
        container.appendChild(div);

        const subsDiv = document.getElementById(`subs-${topic.id}`);
        topic.subtopics.forEach(sub=>{
            const subDiv=document.createElement('div'); subDiv.className='subtopic-block';
            subDiv.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center">
                <div style='flex:1'>
                    <label class='subtopic-pill label-pill'>Subtopic</label>
                    <input type='text' style="color:var(--subtopic-color)" placeholder='Subtopic title' value='${escapeHtml(sub.title)}' oninput="updateSubtopicTitle(${topic.id},${sub.id},this.value)">
                </div>
                <div>
                    <button onclick="addChoice(${topic.id},${sub.id})">+ Choice</button>
                    <button onclick="removeSubtopic(${topic.id},${sub.id})" class='remove-btn'>Delete</button>
                </div>
            </div>
            <label>Question:</label><textarea rows='2' oninput="updateQuestions(${topic.id},${sub.id},this.value)">${escapeHtml(sub.questions)}</textarea>
            <div id='choices-${sub.id}'></div>`;
            subsDiv.appendChild(subDiv);

            // choices
            const choicesDiv = document.getElementById(`choices-${sub.id}`);
            choicesDiv.innerHTML="";
            sub.choices.forEach(c=>{
                const row = document.createElement('div'); row.className='choice-row';
                row.innerHTML=`<input type='radio' name='correct-${sub.id}' ${sub.correctChoice===c.id?'checked':''} onclick="setCorrectChoice(${topic.id},${sub.id},${c.id})"><input type='text' placeholder='Choice text' value='${escapeHtml(c.text)}' oninput="updateChoice(${topic.id},${sub.id},${c.id},this.value)"><button onclick="removeChoice(${topic.id},${sub.id},${c.id})" class='remove-btn'>Remove</button>`;
                choicesDiv.appendChild(row);
            });
        });
    });
    renderPreview();
}

function renderPreview(){
    const preview=document.getElementById('preview');
    preview.innerHTML=topics.map(t=>{
        return `<div style='margin-bottom:14px;'>
            <div style="font-family:Georgia,serif;font-size:20px;color:var(--topic-color);font-weight:bold;">${escapeHtml(t.title)}</div>
            ${t.subtopics.map(s=>`<div style='margin-left:15px;margin-top:10px;'>
                <div style="font-family:'Trebuchet MS',sans-serif;font-size:16px;color:var(--subtopic-color);font-weight:600;">${escapeHtml(s.title)}</div>
                <div style="font-family:Calibri,sans-serif;font-size:14px;margin-top:4px;"><b>Question:</b> <span style="color:#555">${escapeHtml(s.questions)}</span></div>
                <div style="margin-left:10px;margin-top:6px;font-family:Arial,sans-serif;">
                    ${s.choices.map(c=>`<div style='margin-top:4px;color:#333;'><label><input type='radio' disabled ${s.correctChoice===c.id?'checked':''}><i>${escapeHtml(c.text)}</i></label></div>`).join('')}
                </div>
            </div>`).join('')}
        </div>`;
    }).join('');
    renderFilePreview();
    renderImagePreview();
}

function escapeHtml(text){ if(!text && text!==0) return ''; return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// --- Initialize ---
renderForm();
</script>
</body>
</html>
