'use strict';
(() => {
    if (navigator.globalPrivacyControl === true || navigator.doNotTrack === '1') return;
    const pending = new Set(['pageview']);
    let timer;
    function flush() {
        clearTimeout(timer);timer=null;
        if (!pending.size) return;
        const body = JSON.stringify({events:[...pending]});pending.clear();
        fetch('metrics.php',{method:'POST',headers:{'Content-Type':'application/json'},body,credentials:'omit',keepalive:true}).catch(()=>{});
    }
    function count(event) {pending.add(event);if (!timer) timer=setTimeout(flush,5000);}
    timer=setTimeout(flush,5000);
    document.addEventListener('click',event=>{
        const target=event.target.closest('button,a');if (!target) return;
        if (target.classList.contains('read-link')) count('open-story');
        else if (target.classList.contains('save-button') && target.getAttribute('aria-pressed') === 'true') count('save-story');
        else if (target.dataset.view === 'timeline') count('view-timeline');
        else if (target.id === 'surpriseButton') count('surprise');
        else if (target.closest('.project-support') && target.classList.contains('solid-button')) count('support');
        else if (target.closest('.project-support') && target.classList.contains('text-button')) count('source');
    });
    document.querySelector('#datePicker').addEventListener('submit',()=>count('choose-date'));
    document.addEventListener('visibilitychange',()=>{if (document.visibilityState==='hidden') flush();});
    window.addEventListener('pagehide',flush);
})();
