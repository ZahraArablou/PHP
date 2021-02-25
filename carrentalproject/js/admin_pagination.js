window.addEventListener('popstate', (event) => {
    console.log("location: " + document.location + ", state: " + JSON.stringify(event.state));
    loadPage(event.state.page, true);
});

function loadPage(newPageNo, noHistory = false, listPath, singlePagePath) {

    if (newPageNo < 1 || newPageNo > maxPages) return;
    // remove bold from previously current page and put bold on the new currrent page nav link
    $("#pageNav" + currPageNo).css("font-weight", "Normal");
    $("#pageNav" + newPageNo).css("font-weight", "Bold");
    currPageNo = newPageNo;

    // only show prev and next if they are useful
    $("#pageNavPrev").toggle(newPageNo > 1);
    $("#pageNavNext").toggle(newPageNo < maxPages);

    $("#tableBody").load(singlePagePath + currPageNo);
    if (noHistory == false) {
        history.pushState({ page: currPageNo }, '', listPath + currPageNo);
    }
}