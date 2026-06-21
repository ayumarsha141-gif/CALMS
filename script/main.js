const navbar = document.getElementById('navbar');
if (navbar) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 40) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

const navToggle = document.getElementById('navToggle');
const navLinks  = document.querySelector('.nav-links');
const navActions = document.querySelector('.nav-actions');

if (navToggle) {
    navToggle.addEventListener('click', () => {
        navLinks?.classList.toggle('open');
        navActions?.classList.toggle('open');
    });
}

const revealEls = document.querySelectorAll(
    '.feature-card, .lab-card, .panel, .team-member, .cert-item'
);

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }, i * 80);
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

revealEls.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});

const barFills = document.querySelectorAll('.bar-fill');
const barObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const el = entry.target;
            const targetWidth = el.style.width;
            el.style.width = '0%';
            setTimeout(() => { el.style.width = targetWidth; }, 100);
            barObserver.unobserve(el);
        }
    });
}, { threshold: 0.3 });

barFills.forEach(el => barObserver.observe(el));
