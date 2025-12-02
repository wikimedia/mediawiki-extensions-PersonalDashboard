import {test, expect} from 'vitest';
import {mount} from '@vue/test-utils';
import ChangeNumber from '@resources/ext.personalDashboard.riskyArticleEdits/components/ChangeNumber.vue';


test('mount component', () => {
	const wrapper = mount(ChangeNumber, {
		props: {
			newlen: 10,
			oldlen: 5,
		},
	});

	expect(wrapper.element).toMatchSnapshot();
});

test('Change number is formatted correctly when no change', () => {
	const wrapper = mount(ChangeNumber, {
		props: {
			newlen: 10,
			oldlen: 10,
		},
	});
	expect(wrapper.vm.changeValue).toBe(0);
	expect(wrapper.vm.changeClass).toBe("ext-personal-dashboard-moderation-card-info-change-number-none")
	expect(wrapper.vm.changeNumber).toBe("0")
})

test('Change number is formatted correctly when negative change', () => {
	const wrapper = mount(ChangeNumber, {
		props: {
			newlen: 5,
			oldlen: 10,
		},
	});
	expect(wrapper.vm.changeValue).toBe(-5);
	expect(wrapper.vm.changeClass).toBe("ext-personal-dashboard-moderation-card-info-change-number-negative")
	expect(wrapper.vm.changeNumber).toBe("-5")
})

test('Change number is formatted correctly when positive change', () => {
	const wrapper = mount(ChangeNumber, {
		props: {
			newlen: 15,
			oldlen: 10,
		},
	});
	expect(wrapper.vm.changeValue).toBe(5);
	expect(wrapper.vm.changeClass).toBe("ext-personal-dashboard-moderation-card-info-change-number-positive")
	expect(wrapper.vm.changeNumber).toBe("+5")
})

