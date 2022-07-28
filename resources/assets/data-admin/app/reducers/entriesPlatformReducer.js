import {
    FETCH_ENTRIES_STATS
} from 'config/actions';

const initialState = {
    android: null,
    ios: null,
    web: null,
    unknown: null,
    wasRejected: null,
    error: ''
};

export default function entriesPlatformReducer(state = initialState, action) {

    switch (action.type) {
        case FETCH_ENTRIES_STATS + '_PENDING':
        {
            return {
                ...state
            };
        }
        case FETCH_ENTRIES_STATS + '_FULFILLED':
        {
            const stats = action.payload.data.data;

            return {
                ...state,
                unknown: stats.entries.by_platform.unknown + stats.branch_entries.by_platform.unknown,
                android: stats.entries.by_platform.android + stats.branch_entries.by_platform.android,
                ios: stats.entries.by_platform.ios + stats.branch_entries.by_platform.ios,
                web: stats.entries.by_platform.web + stats.branch_entries.by_platform.web,
                wasRejected: false
            };
        }
        case FETCH_ENTRIES_STATS + '_REJECTED':
        {
            return {
                ...state,
                wasRejected: true,
                error: action.payload.message
            };
        }
        default:
            return state;
    }
}
