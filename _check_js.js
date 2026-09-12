const fs = require('fs');
for (const file of process.argv.slice(2)) {
  const s = fs.readFileSync(file, 'utf8');
  if (file.endsWith('.php')) {
    // extract inline <script> blocks, strip PHP tags inside them
    const re = /<script>([\s\S]*?)<\/script>/g;
    let m, i = 0;
    while ((m = re.exec(s)) !== null) {
      const js = m[1].replace(/<\?(?:php|=)[\s\S]*?\?>/g, 'null');
      ++i;
      const tag = js.includes('LiveEngagement.init') ? '[JOIN]' : (js.includes('LE_API_BASE') ? '[LAYOUT]' : '[other]');
      try { new Function(js); console.log(file + ' block ' + i + ' ' + tag + ' OK, len=' + js.length); }
      catch (e) { console.log(file + ' block ' + i + ' ' + tag + ' JS ERROR: ' + e.message); process.exitCode = 1; }
    }
    if (i === 0) console.log(file + ': no inline scripts');
  } else {
    try { new Function(s); console.log(file + ' OK, len=' + s.length); }
    catch (e) { console.log(file + ' JS ERROR: ' + e.message); process.exitCode = 1; }
  }
}