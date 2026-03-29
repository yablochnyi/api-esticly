document.addEventListener('DOMContentLoaded', function() {
  const dots = document.querySelectorAll('.reviews__dot');
  const prevBtn = document.querySelector('.reviews__pagination-btn--prev');
  const nextBtn = document.querySelector('.reviews__pagination-btn--next');

  if (!dots.length) return;

  let currentIndex = 0;

  function setActive(index) {
    dots.forEach((dot, i) => {
      if (i === index) {
        dot.classList.add('reviews__dot--active');
      } else {
        dot.classList.remove('reviews__dot--active');
      }
    });
    currentIndex = index;
  }

  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      setActive(index);
    });
  });

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      const newIndex = (currentIndex - 1 + dots.length) % dots.length;
      setActive(newIndex);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      const newIndex = (currentIndex + 1) % dots.length;
      setActive(newIndex);
    });
  }
});



document.addEventListener('DOMContentLoaded', function() {
  const faqItems = document.querySelectorAll('.faq__item');

  if (!faqItems.length) return;

  faqItems.forEach(item => {
    const question = item.querySelector('.faq__question');

    question.addEventListener('click', () => {
      const isActive = item.classList.contains('active');

      faqItems.forEach(otherItem => {
        otherItem.classList.remove('active');
      });

      if (!isActive) {
        item.classList.add('active');
      }
    });
  });
});

const menuBtn = document.getElementById('menuBtn');
const closeBtn = document.getElementById('closeBtn');
const mobileMenu = document.getElementById('mobileMenu');

menuBtn?.addEventListener('click', () => {
  menuBtn.classList.toggle('active');
  mobileMenu?.classList.toggle('active');
});

closeBtn?.addEventListener('click', () => {
  menuBtn?.classList.remove('active');
  mobileMenu?.classList.remove('active');
});

const links = document.querySelectorAll('.header__link');
links.forEach(link => {
  link.addEventListener('click', () => {
    menuBtn?.classList.remove('active');
    mobileMenu?.classList.remove('active');
  });
});
