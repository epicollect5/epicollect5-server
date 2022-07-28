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

export default function entriesMonthReducer(state = initialState, action) {

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
            const entriesMonth = stats.entries.month.private + stats.entries.month.public;
            const branchEntriesMonth = stats.branch_entries.month.private + stats.branch_entries.month.public;
            const entriesMonthOverall = entriesMonth + branchEntriesMonth;
            const entriesMonthPublic = stats.entries.month.public + stats.branch_entries.month.public;
            const entriesMonthPrivate = stats.entries.month.private + stats.branch_entries.month.private;

            return {
                ...state,
                wasRejected: false,
                overall: entriesMonthOverall,
                private: entriesMonthPublic,
                public: entriesMonthPrivate
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
