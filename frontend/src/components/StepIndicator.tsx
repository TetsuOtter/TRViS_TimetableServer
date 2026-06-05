// StepIndicator.tsx — Wizard step indicator component
import { Fragment } from "react";

export type StepIndicatorProps = {
	readonly step: number;
	readonly labels: string[];
};

export const StepIndicator = ({ step, labels }: StepIndicatorProps) => {
	return (
		<div className="wizard-steps">
			{labels.map((lbl, i) => (
				<Fragment key={lbl}>
					{i > 0 && <div className="wizard-connector" />}
					<div
						className={`wizard-step ${
							step === i ? "active" : step > i ? "done" : ""
						}`}>
						<div className="wizard-step-num">{step > i ? "✓" : i + 1}</div>
						<div className="wizard-step-label">{lbl}</div>
					</div>
				</Fragment>
			))}
		</div>
	);
};
