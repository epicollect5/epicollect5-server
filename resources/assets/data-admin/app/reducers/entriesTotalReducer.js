import {
    FETCH_ENTRIES_STATS
} from 'config/actions';

const initialState = {
    overall: null,
    private: null,
    public: null,
    wasRejected: null,
    error: ''
};

export default function entriesTotalReducer(state = initialState, action) {

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
            const entriesTotal = stats.entries.total.private + stats.entries.total.public;
            const branchEntriesTotal = stats.branch_entries.total.private + stats.branch_entries.total.public;
            const entriesTotalOverall = entriesTotal + branchEntriesTotal;
            const entriesTotalPublic = stats.entries.total.public + stats.branch_entries.total.public;
            const entriesTotalPrivate = stats.entries.total.private + stats.branch_entries.total.private;

            return {
                ...state,
                wasRejected: false,
                overall: entriesTotalOverall,
                private: entriesTotalPublic,
                public: entriesTotalPrivate
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
