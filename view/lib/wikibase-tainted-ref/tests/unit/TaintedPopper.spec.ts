import { createLocalVue, mount } from '@vue/test-utils';
import Vuex, { Store } from 'vuex';
import Track from '@/vue-plugins/Track';
import Message from '@/vue-plugins/Message';
import Application from '@/store/Application';
import TaintedPopper from '@/presentation/components/TaintedPopper.vue';
import { POPPER_HIDE, STATEMENT_TAINTED_STATE_UNTAINT } from '@/store/actionTypes';
import { GET_FEEDBACK_LINK, GET_HELP_LINK } from '@/store/getterTypes';

const localVue = createLocalVue();
const trackingFunction: any = jest.fn();
localVue.use( Vuex );
localVue.use( Message, { messageToTextFunction: () => {
	return 'dummy';
} } );
localVue.use( Track, { trackingFunction } );

function createMockStore( helpLink?: string ): Store<Partial<Application>> {
	return new Store<Partial<Application>>( {
		actions: {
			[ STATEMENT_TAINTED_STATE_UNTAINT ]: jest.fn(),
		},
		getters: {
			[ GET_FEEDBACK_LINK ]: jest.fn(),
			[ GET_HELP_LINK ]: helpLink ? () => helpLink : jest.fn(),
		},
	} );
}

describe( 'TaintedPopper.vue', () => {
	it( 'sets the help link according to the store', () => {
		const helpLinkUrl = 'https://wdtest/Help';
		const store = createMockStore( helpLinkUrl );
		const wrapper = mount( TaintedPopper, {
			store,
			localVue,
		} );
		expect( wrapper.find( '.wb-tr-popper-help a' ).attributes().href ).toEqual( helpLinkUrl );
	} );
	it( 'clicking the help link triggers a tracking event', () => {
		const store = createMockStore();
		const wrapper = mount( TaintedPopper, {
			store,
			localVue,
		} );
		wrapper.find( '.wb-tr-popper-help a' ).trigger( 'click' );
		expect( trackingFunction ).toHaveBeenCalledWith( 'counter.wikibase.view.tainted-ref.helpLinkClick', 1 );
	} );
	it( 'does not close the popper when the help link is focused', () => {
		const store = createMockStore();
		store.dispatch = jest.fn();

		const wrapper = mount( TaintedPopper, {
			store,
			localVue,
			propsData: { guid: 'a-guid' },
		} );
		wrapper.trigger(
			'focusout', {
				relatedTarget: wrapper.find( '.wb-tr-popper-help' ).element,
			},
		);
		expect( store.dispatch ).not.toHaveBeenCalledWith( POPPER_HIDE, 'a-guid' );
	} );
	it( 'clicking the remove warning button untaints the statements', () => {
		const store = createMockStore();
		store.dispatch = jest.fn();

		const wrapper = mount( TaintedPopper, {
			store,
			localVue,
			propsData: { guid: 'a-guid' },
		} );
		wrapper.find( '.wb-tr-popper-remove-warning' ).trigger( 'click' );
		expect( store.dispatch ).toHaveBeenCalledWith( STATEMENT_TAINTED_STATE_UNTAINT, 'a-guid' );
	} );
	it( 'clicking the remove warning button triggers a tracking event', () => {
		const store = createMockStore();
		const wrapper = mount( TaintedPopper, {
			store,
			localVue,
		} );
		wrapper.find( '.wb-tr-popper-remove-warning' ).trigger( 'click' );
		expect( trackingFunction ).toHaveBeenCalledWith( 'counter.wikibase.view.tainted-ref.removeWarningClick', 1 );
	} );
	it( 'popper texts are taken from our Vue message plugin', () => {
		const localVue = createLocalVue();
		const messageToTextFunction = jest.fn();
		messageToTextFunction.mockImplementation( ( key ) => `(${key})` );

		localVue.use( Vuex );
		localVue.use( Message, { messageToTextFunction } );

		const store = createMockStore();
		store.dispatch = jest.fn();

		const wrapper = mount( TaintedPopper, {
			store,
			localVue,
			propsData: { guid: 'a-guid' },
		} );
		expect( wrapper.find( '.wb-tr-popper__text--top' ).element.textContent )
			.toMatch( '(wikibase-tainted-ref-popper-text)' );
		expect( wrapper.find( '.wb-tr-popper-title' ).element.textContent )
			.toMatch( '(wikibase-tainted-ref-popper-title)' );
		expect( wrapper.find( '.wb-tr-popper-help a' ).element.title )
			.toMatch( '(wikibase-tainted-ref-popper-help-link-title)' );
		expect( wrapper.find( '.wb-tr-popper-help' ).element.textContent )
			.toMatch( '(wikibase-tainted-ref-popper-help-link-text)' );
		expect( wrapper.find( '.wb-tr-popper-feedback' ).element.textContent )
			.toMatch( '(wikibase-tainted-ref-popper-feedback-text)' );
		expect( wrapper.find( '.wb-tr-popper-feedback a' ).element.title )
			.toMatch( '(wikibase-tainted-ref-popper-feedback-link-title)' );
		expect( wrapper.find( '.wb-tr-popper-feedback a' ).element.textContent )
			.toMatch( '(wikibase-tainted-ref-popper-feedback-link-text)' );
		expect( wrapper.find( '.wb-tr-popper-remove-warning' ).element.textContent )
			.toMatch( '(wikibase-tainted-ref-popper-remove-warning)' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-text' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-title' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-help-link-title' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-help-link-text' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-feedback-text' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-feedback-link-text' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-feedback-link-title' );
		expect( messageToTextFunction ).toHaveBeenCalledWith( 'wikibase-tainted-ref-popper-remove-warning' );
	} );
} );
