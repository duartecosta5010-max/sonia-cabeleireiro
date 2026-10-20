document.addEventListener("DOMContentLoaded", () => {
  const items = document.querySelectorAll(".reveal");
  if (!items.length) return;

  // Guardar um delay por elemento (efeito "stagger" no fade-in)
  items.forEach((el, idx) => {
    const delay = Math.min(idx * 70, 280); // máximo 280ms
    el.dataset.revealDelay = String(delay);
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const el = entry.target;

        if (entry.isIntersecting) {
          // Fade-in (com delay)
          el.style.transitionDelay = `${el.dataset.revealDelay || 0}ms`;
          el.classList.add("is-visible");
        } else {
          // Fade-out (sem delay para não "ficar preso")
          el.style.transitionDelay = "0ms";
          el.classList.remove("is-visible");
        }
      });
    },
    {
      threshold: 0.15,              // percentagem visível para contar como "apareceu"
      rootMargin: "0px 0px -5% 0px" // faz aparecer um bocadinho antes
    }
  );

  items.forEach((el) => observer.observe(el));
});