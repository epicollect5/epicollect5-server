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

export default function entriesYearReducer(state = initialState, action) {

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
            const entriesYear = stats.entries.year.private + stats.entries.year.public;
            const branchEntriesYear = stats.branch_entries.year.private + stats.branch_entries.year.public;
            const entriesYearOverall = entriesYear + branchEntriesYear;
            const entriesYearPublic = stats.entries.year.public + stats.branch_entries.year.public;
            const entriesYearPrivate = stats.entries.year.private + stats.branch_entries.year.private;

            return {
                ...state,
                wasRejected: false,
                overall: entriesYearOverall,
                private: entriesYearPublic,
                public: entriesYearPrivate
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
