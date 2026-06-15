

<style>
.ri-footer {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding: 2rem 1.5rem;
  margin-top: auto;
  box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
}

.ri-footer__container {
  max-width: 1400px;
  margin: 0 auto;
}

.ri-footer__content {
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  align-items: center;
}

.ri-footer__text {
  color: #cbd5e1;
  font-size: 0.95rem;
  margin: 0;
  font-weight: 400;
  letter-spacing: 0.2px;
  line-height: 1.6;
}

.ri-footer__link {
  color: #60a5fa;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s ease;
  position: relative;
  display: inline-block;
  padding: 0 2px;
}

.ri-footer__link:hover {
  color: #93c5fd;
  transform: translateY(-1px);
}

.ri-footer__link::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 0;
  width: 0;
  height: 2px;
  background: linear-gradient(90deg, #60a5fa, #3b82f6);
  transition: width 0.3s ease;
}

.ri-footer__link:hover::after {
  width: 100%;
}

.ri-footer__reg {
  font-size: 0.7em;
  vertical-align: super;
  margin-left: 1px;
  color: #94a3b8;
}

.ri-footer__rights {
  color: #94a3b8;
  font-size: 0.85rem;
  margin: 0;
  font-weight: 300;
}

@media (max-width: 768px) {
  .ri-footer {
    padding: 1.5rem 1rem;
  }
  
  .ri-footer__text {
    font-size: 0.875rem;
  }
  
  .ri-footer__rights {
    font-size: 0.8rem;
  }
}

@media (max-width: 480px) {
  .ri-footer {
    padding: 1.25rem 0.875rem;
  }
  
  .ri-footer__content {
    gap: 0.4rem;
  }
  
  .ri-footer__text {
    font-size: 0.8125rem;
    line-height: 1.5;
  }
  
  .ri-footer__rights {
    font-size: 0.75rem;
  }
}

@media (max-width: 360px) {
  .ri-footer__text {
    font-size: 0.75rem;
  }
  
  .ri-footer__rights {
    font-size: 0.7rem;
  }
}
</style>