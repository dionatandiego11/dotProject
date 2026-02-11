export default function WizardStep({ label, index, currentStep }) {
    let state = 'pending'
    if (index < currentStep) state = 'completed'
    if (index === currentStep) state = 'active'

    return (
        <div className={`setup-wizard__step-indicator ${state}`}>
            <div className={`setup-wizard__step-dot ${state}`}>
                {state === 'completed' ? '\u2713' : index + 1}
            </div>
            <span className={`setup-wizard__step-label ${state}`}>{label}</span>
        </div>
    )
}
